import { useEffect } from 'react';
import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import type {
  ShiftsPrintPageProps,
  ShiftTable,
  ShiftTableCell,
  ShiftTableDay,
  ShiftTableGroup,
} from '@/types/shift';

const TONE_STYLES: Record<ShiftTableDay['tone'], { background: string; color: string }> = {
  red: { background: '#FEE2E2', color: '#B91C1C' },
  blue: { background: '#DBEAFE', color: '#1D4ED8' },
  normal: { background: '#F3F4F6', color: '#111827' },
};

/**
 * 印刷用のスタイル。
 * A4横・余白小さめにし、ブラウザの既定では省かれる背景色も印刷する（シフトの色が大事なため）。
 * 部署の途中でページが変わっても、日付の見出しは各ページの先頭に繰り返す。
 */
const PRINT_CSS = `
  @page { size: A4 landscape; margin: 8mm; }
  html, body { background: #fff; }
  .shift-print { color: #111827; font-family: "Hiragino Sans", "Yu Gothic UI", "Meiryo", system-ui, sans-serif; }
  .shift-print table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  .shift-print th, .shift-print td { border: 1px solid #D1D5DB; padding: 1px 0; text-align: center; vertical-align: middle; font-size: 8px; line-height: 1.15; word-break: break-all; overflow: hidden; }
  .shift-print td.name { text-align: left; padding-left: 3px; font-size: 9px; white-space: nowrap; background: #EFF6FF; }
  .shift-print tr { page-break-inside: avoid; break-inside: avoid; }
  .shift-print thead { display: table-header-group; }
  .shift-print .department td { text-align: left; padding: 2px 3px; font-weight: 700; font-size: 9px; background: #E5E7EB; color: #374151; }
  .shift-print .subtotal td { background: #F0FDF4; color: #166534; font-weight: 700; }
  .shift-print .subtotal td.name { background: #DCFCE7; }
  .shift-print .total td { background: #DCFCE7; color: #166534; font-weight: 700; border-top: 2px solid #6B7280; }
  .shift-print .small { font-size: 6.5px; font-weight: 700; }
  .shift-print, .shift-print * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  @media print { .no-print { display: none !important; } .shift-print-body { padding: 0 !important; } }
`;

function formatCount(count: number): string {
  return `${Number.isInteger(count) ? count : Number(count.toFixed(2))}人`;
}

function periodLabel(table: ShiftTable): string {
  const [sy, sm, sd] = table.start_date.split('-').map(Number);
  const [ey, em, ed] = table.end_date.split('-').map(Number);
  return sy === ey
    ? `${sy}年${sm}月${sd}日〜${em}月${ed}日`
    : `${sy}年${sm}月${sd}日〜${ey}年${em}月${ed}日`;
}

function ShiftCell({ cell }: { cell: ShiftTableCell }) {
  const leave = cell.leave;

  // 全日の休暇は、セル全体を休暇の表示にする
  if (leave?.is_full_day) {
    return (
      <td style={{ background: leave.background, color: leave.text, fontWeight: 700 }}>{leave.label}</td>
    );
  }

  return (
    <td style={{ background: cell.background, color: cell.text }}>
      {cell.label}
      {leave && (
        // 半日有給・時間有給は、シフト名の下に略称を添える
        <div className="small" style={{ background: leave.background, color: leave.text }}>
          {leave.label}
        </div>
      )}
    </td>
  );
}

function GroupRows({ group, table }: { group: ShiftTableGroup; table: ShiftTable }) {
  const span = table.days.length + 3;

  return (
    <>
      <tr className="department">
        <td colSpan={span}>{group.name}</td>
      </tr>
      {group.users.map((user) => (
        <tr key={user.id} style={{ height: '7.5mm' }}>
          <td className="name">{user.name}</td>
          {table.days.map((day) => (
            <ShiftCell key={day.date} cell={user.cells[day.date]} />
          ))}
          <td style={{ background: '#DCFCE7', color: '#166534', fontWeight: 700 }}>{user.work_days}日</td>
          <td style={{ background: '#FFEDD5', color: '#9A3412', fontWeight: 700 }}>{user.rest_days}日</td>
        </tr>
      ))}
      <tr className="subtotal">
        <td className="name">{group.name} 合計</td>
        {table.days.map((day) => (
          <td key={day.date}>{formatCount(group.counts[day.date])}</td>
        ))}
        <td colSpan={2} />
      </tr>
    </>
  );
}

interface ShiftTableBlockProps {
  table: ShiftTable;
  groups: ShiftTableGroup[];
  /** 全部署の合計の行を末尾に出すか */
  showTotal: boolean;
}

/**
 * 日付の見出しと、指定した部署のスタッフの行を並べた表
 *
 * 見出しは、表がページをまたいでも各ページの先頭に繰り返す。
 */
function ShiftTableBlock({ table, groups, showTotal }: ShiftTableBlockProps) {
  return (
    <table>
      <colgroup>
        <col style={{ width: '24mm' }} />
        {table.days.map((day) => (
          <col key={day.date} />
        ))}
        <col style={{ width: '9mm' }} />
        <col style={{ width: '9mm' }} />
      </colgroup>
      <thead>
        <tr>
          <th rowSpan={2} style={{ background: '#F3F4F6' }}>スタッフ</th>
          {table.days.map((day, index) => (
            <th key={day.date} style={TONE_STYLES[day.tone]}>
              {/* 期間の最初の日と月の1日は、月も添えて月をまたぐことが分かるようにする */}
              {index === 0 || day.day === 1 ? `${day.month}/${day.day}` : day.day}
            </th>
          ))}
          <th rowSpan={2} style={{ background: '#DCFCE7', color: '#166534' }}>出勤</th>
          <th rowSpan={2} style={{ background: '#FFEDD5', color: '#9A3412' }}>休日</th>
        </tr>
        <tr>
          {table.days.map((day) => (
            <th key={day.date} style={TONE_STYLES[day.tone]}>
              {day.weekday}
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {groups.map((group) => (
          <GroupRows key={group.department_id ?? 'none'} group={group} table={table} />
        ))}
        {groups.length === 0 && (
          <tr>
            <td colSpan={table.days.length + 3} style={{ padding: '8px' }}>
              対象のスタッフがいません。
            </td>
          </tr>
        )}
        {showTotal && groups.length > 0 && (
          <tr className="total">
            <td className="name" style={{ background: '#BBF7D0' }}>合計</td>
            {table.days.map((day) => (
              <td key={day.date}>{formatCount(table.totals.counts[day.date])}</td>
            ))}
            <td>{table.totals.work_days}日</td>
            <td style={{ background: '#FED7AA', color: '#9A3412' }}>{table.totals.rest_days}日</td>
          </tr>
        )}
      </tbody>
    </table>
  );
}

function Legend({ table }: { table: ShiftTable }) {
  return (
    <div style={{ marginTop: '6px', display: 'flex', flexWrap: 'wrap', gap: '4px 10px', fontSize: '8px', alignItems: 'center' }}>
      <span style={{ fontWeight: 700 }}>凡例</span>
      {table.patterns.map((pattern) => (
        <span key={pattern.name} style={{ display: 'inline-flex', alignItems: 'center', gap: '3px' }}>
          <span style={{ background: pattern.background, color: pattern.text, border: '1px solid #D1D5DB', padding: '0 4px' }}>
            {pattern.name}
          </span>
          {pattern.start_time && pattern.end_time ? `${pattern.start_time}〜${pattern.end_time}` : ''}
        </span>
      ))}
      <span style={{ color: '#4B5563' }}>有：有給休暇　特：特別休暇　半：半日有給　時：時間有給（承認済みの休暇）</span>
    </div>
  );
}

const HEADING_STYLE = { fontSize: '14px', fontWeight: 700, marginBottom: '4px' } as const;

/**
 * シフト表の印刷用ページ（全部署、または選択した部署）
 *
 * シフト管理画面と同じ見え方のシフト表を、A4横の用紙にそのまま印刷できる形で表示する。
 * ページを開くと印刷ダイアログが開く。全部署のとき、pageBreak が真なら部署ごとにページを分ける
 * （部署ごとに配るときなどのため）。
 */
export default function ShiftsPrint({ table, pageBreak }: ShiftsPrintPageProps) {
  // 開いたらすぐ印刷できるようにする（表を描画し終えてから）
  useEffect(() => {
    const timer = window.setTimeout(() => window.print(), 400);
    return () => window.clearTimeout(timer);
  }, []);

  const period = periodLabel(table);
  const title = `シフト表　${period}　${table.department_name ?? '全部署'}`;
  // 部署が1つだけのときは、ページを分ける意味がない
  const separatePages = pageBreak && table.groups.length > 1;
  // 全体の合計は、複数の部署があるときだけ出す（1つだけなら部署の合計と同じ）
  const showTotal = table.groups.length > 1;

  return (
    <>
      <Head title="シフト表の印刷" />
      <style>{PRINT_CSS}</style>

      <div className="no-print sticky top-0 z-10 flex items-center justify-between gap-3 bg-gray-800 px-4 py-2 text-white">
        <span className="text-sm">印刷ダイアログが開かない場合は、右のボタンを押してください（用紙はA4横）。</span>
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => window.print()}
            className="flex items-center space-x-2 rounded-md bg-blue-600 px-4 py-1.5 text-sm font-medium hover:bg-blue-700"
          >
            <Printer className="h-4 w-4" />
            <span>印刷する</span>
          </button>
          <button
            type="button"
            onClick={() => window.close()}
            className="rounded-md bg-gray-600 px-4 py-1.5 text-sm font-medium hover:bg-gray-500"
          >
            閉じる
          </button>
        </div>
      </div>

      <div className="shift-print shift-print-body overflow-x-auto bg-white p-4">
        {separatePages ? (
          <>
            {table.groups.map((group, index) => (
              <section
                key={group.department_id ?? 'none'}
                className="department-page"
                style={index > 0 ? { breakBefore: 'page', pageBreakBefore: 'always' } : undefined}
              >
                {/* 部署ごとに配れるよう、各ページの先頭に部署名を入れる */}
                <h1 style={HEADING_STYLE}>{`シフト表　${period}　${group.name}`}</h1>
                <ShiftTableBlock table={table} groups={[group]} showTotal={showTotal && index === table.groups.length - 1} />
                {index === table.groups.length - 1 && <Legend table={table} />}
              </section>
            ))}
          </>
        ) : (
          <>
            <h1 style={HEADING_STYLE}>{title}</h1>
            <ShiftTableBlock table={table} groups={table.groups} showTotal={showTotal} />
            <Legend table={table} />
          </>
        )}
      </div>
    </>
  );
}
