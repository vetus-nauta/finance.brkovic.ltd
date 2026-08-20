import Link from "next/link";
import { routes } from "@/lib/routes";

const foundationBlocks = [
  {
    title: "Оперативный журнал",
    text: "Истина финансовой ленты. В будущем открывает записи, заметки, отчеты и корректировки."
  },
  {
    title: "Сводка",
    text: "Считает только из подтвержденных операций и отчетных снимков, без ручных итогов."
  },
  {
    title: "Заметки",
    text: "Быстрый ввод, который Mr. Smith разбирает и предлагает перенести в журнал."
  },
  {
    title: "Отчеты сотрудников",
    text: "Выдача под отчет, принятие расходов, возвраты и возмещение перерасхода."
  }
];

export default function WorkspacesPage() {
  return (
    <main className="page compact-page">
      <section className="section-head">
        <div>
          <p className="eyebrow">Claudia Z</p>
          <h1>Рабочая область</h1>
          <p>Продуктовый каркас будущего web-клиента. Данные подключаются через команды и RLS.</p>
        </div>
        <Link className="ghost-button" href={routes.hall}>
          В холл
        </Link>
      </section>

      <section className="workspace-shell">
        <aside className="side-tabs" aria-label="Разделы рабочего пространства">
          <button className="active" type="button">
            Кеш
          </button>
          <button type="button">Карта</button>
          <button type="button">Заметки</button>
          <button type="button">Отчеты</button>
        </aside>
        <div className="workspace-main">
          {foundationBlocks.map((block) => (
            <article className="panel" key={block.title}>
              <h2>{block.title}</h2>
              <p>{block.text}</p>
            </article>
          ))}
        </div>
      </section>
    </main>
  );
}
