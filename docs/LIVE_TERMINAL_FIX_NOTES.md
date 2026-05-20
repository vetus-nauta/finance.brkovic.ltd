# Brkovic Ops / Live Terminal — Fix Notes After Quick Ledger Session

## Context

During Quick Ledger setup on `/home/brkovic/finance.brkovic.ltd`, the existing Brkovic Ops Live Terminal failed when executing larger multi-line shell blocks with heredocs and SQL/PHP payloads.

The user pasted valid long shell blocks into Live Terminal. Instead of returning command output JSON, the frontend received HTML and showed:

```text
Request error: Unexpected token '<', "<!DOCTYPE "... is not valid JSON
FAIL | exit — | 0 ms
