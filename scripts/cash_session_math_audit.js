const {
  parseCashNotebook,
  cashParticipantTotals,
  cashSettlementLines,
} = require('../server/findesk-atlas-server');

const EPS = 0.009;

function signedMoney(value) {
  return Math.round(Number(value || 0) * 100) / 100;
}

function participant(id, name, included = true) {
  return {
    id,
    display_name: name,
    role: id === 'owner' ? 'treasurer' : 'participant',
    included_in_split: included,
    active: true,
  };
}

function batch(participantId, name, text) {
  return {
    id: `audit_${participantId}_${Date.now()}`,
    participant_id: participantId,
    participant_display_name: name,
    entries: parseCashNotebook(text),
    created_at: new Date().toISOString(),
  };
}

function session({ participants, batches }) {
  return {
    id: 0,
    workspace_id: 0,
    title: 'Cash math audit fixture',
    preset: 'audit',
    mode: 'group',
    status: 'active',
    currency: 'EUR',
    participants,
    batches,
  };
}

function normalizeLines(lines) {
  return lines.map((line) => ({
    from: line.from_participant_id,
    to: line.to_participant_id,
    amount: signedMoney(line.amount),
  }));
}

function compareLines(actual, expected) {
  if (actual.length !== expected.length) return false;
  return expected.every((line, index) => (
    actual[index]
    && actual[index].from === line.from
    && actual[index].to === line.to
    && Math.abs(actual[index].amount - line.amount) <= EPS
  ));
}

function scenario(name, fixture, expected) {
  const totals = cashParticipantTotals(session(fixture));
  const lines = normalizeLines(cashSettlementLines(totals));
  const participantBalances = Object.fromEntries(Object.entries(totals.participants || {}).map(([id, item]) => [id, signedMoney(item.balance)]));
  const balanceSum = signedMoney(Object.values(participantBalances).reduce((sum, value) => sum + value, 0));
  const lineTotal = signedMoney(lines.reduce((sum, line) => sum + line.amount, 0));
  const expectedLinesOk = compareLines(lines, expected.lines || []);
  const balanceInvariantOk = Math.abs(balanceSum - Number(expected.balance_sum || 0)) <= EPS;
  const status = expected.requires_review ? 'requires_review' : 'pass';
  return {
    name,
    status,
    ok: expected.requires_review ? expectedLinesOk : (expectedLinesOk && balanceInvariantOk),
    totals: {
      participant_count: totals.participant_count,
      total_contributions: totals.total_contributions,
      total_expenses: totals.total_expenses,
      share: totals.share,
      balance_sum: balanceSum,
      line_total: lineTotal,
    },
    participant_balances: participantBalances,
    settlement_lines: lines,
    expected: {
      lines: expected.lines || [],
      balance_sum: expected.balance_sum || 0,
    },
    note: expected.note || '',
  };
}

const fixtures = [
  scenario('two_people_one_expense', {
    participants: [
      participant('owner', 'Captain'),
      participant('guest', 'Guest'),
    ],
    batches: [
      batch('owner', 'Captain', '-100 provisions'),
    ],
  }, {
    lines: [{ from: 'guest', to: 'owner', amount: 50 }],
    balance_sum: 0,
  }),
  scenario('two_people_equal_expenses', {
    participants: [
      participant('owner', 'Captain'),
      participant('guest', 'Guest'),
    ],
    batches: [
      batch('owner', 'Captain', '-50 taxi'),
      batch('guest', 'Guest', '-50 lunch'),
    ],
  }, {
    lines: [],
    balance_sum: 0,
  }),
  scenario('three_people_mixed_expenses', {
    participants: [
      participant('owner', 'Captain'),
      participant('guest_a', 'Guest A'),
      participant('guest_b', 'Guest B'),
    ],
    batches: [
      batch('owner', 'Captain', '-120 marina'),
      batch('guest_a', 'Guest A', '-30 groceries'),
    ],
  }, {
    lines: [
      { from: 'guest_a', to: 'owner', amount: 20 },
      { from: 'guest_b', to: 'owner', amount: 50 },
    ],
    balance_sum: 0,
  }),
  scenario('excluded_viewer_does_not_split', {
    participants: [
      participant('owner', 'Captain'),
      participant('guest', 'Guest'),
      participant('viewer', 'Viewer', false),
    ],
    batches: [
      batch('owner', 'Captain', '-80 fuel'),
      batch('viewer', 'Viewer', '-20 note-only-service'),
    ],
  }, {
    lines: [
      { from: 'guest', to: 'owner', amount: 30 },
      { from: 'guest', to: 'viewer', amount: 20 },
    ],
    balance_sum: 0,
    requires_review: true,
    note: 'Excluded participant expenses are reimbursed while the participant is excluded from share. This needs policy review.',
  }),
  scenario('contribution_creates_surplus_review', {
    participants: [
      participant('owner', 'Captain'),
      participant('guest', 'Guest'),
    ],
    batches: [
      batch('owner', 'Captain', '+100 cash in'),
      batch('owner', 'Captain', '-60 provisions'),
    ],
  }, {
    lines: [{ from: 'guest', to: 'owner', amount: 30 }],
    balance_sum: 100,
    requires_review: true,
    note: 'Current preview treats contributions as participant credit. Settlement lines do not allocate remaining cash surplus.',
  }),
];

const parserChecks = [
  { line: '+100 advance', kind: 'contribution', amount: 100 },
  { line: '40 fuel', kind: 'note', amount: 0 },
  { line: '-40 fuel', kind: 'expense', amount: -40 },
  { line: '=40 fuel', kind: 'note', amount: 0 },
  { line: '_40 fuel', kind: 'note', amount: 0 },
  { line: 'note: receipt later', kind: 'note', amount: 0 },
].map((check) => {
  const entry = parseCashNotebook(check.line)[0] || {};
  const amount = signedMoney(entry.amount || 0);
  const ok = entry.entry_kind === check.kind && Math.abs(amount - check.amount) <= EPS;
  return {
    line: check.line,
    ok,
    expected: { kind: check.kind, amount: check.amount },
    actual: { kind: entry.entry_kind || '', amount },
  };
});

const failed = fixtures.filter((item) => !item.ok);
const parserFailed = parserChecks.filter((item) => !item.ok);
const review = fixtures.filter((item) => item.status === 'requires_review');

console.log(JSON.stringify({
  checked_at: new Date().toISOString(),
  audit_status: 'preview_not_final',
  parser_rule: '+number text is income; -number text is expense; unsigned number text and any other prefix/text are notes outside calculation.',
  parser_checks: parserChecks,
  scenarios: fixtures,
  summary: {
    total: fixtures.length,
    pass: fixtures.length - failed.length,
    failed: failed.length,
    parser_failed: parserFailed.length,
    requires_review: review.length,
  },
}, null, 2));

if (failed.length || parserFailed.length) process.exit(1);
