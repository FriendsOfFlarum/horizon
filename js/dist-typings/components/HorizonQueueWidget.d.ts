import QueueWidget from 'flarum/admin/components/QueueWidget';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
/**
 * The horizon enrichment block carried inside `/queue/stats` totals, produced
 * by HorizonQueueStatsProvider on the backend. Optional so the widget degrades
 * gracefully if an older backend (no enrichment) responds.
 */
interface HorizonBlock {
    processes: number;
    supervisors: number;
    jobsPerMinute: number;
    maxWait: {
        queue: string;
        seconds: number;
    } | null;
    paused: boolean;
}
/**
 * Horizon's admin-dashboard card.
 *
 * Rather than ship a parallel widget, this extends core's generic QueueWidget
 * so the pending/reserved/failed counts (and the failed-jobs drill-through)
 * come from the one shared `/queue/stats` endpoint — which, with horizon
 * active, is backed by HorizonQueueStatsProvider. We then add horizon-specific
 * tiles (worker processes, throughput, longest wait, paused state) from the
 * `horizon` enrichment block core's own widget ignores. Deep metrics
 * (throughput history, per-queue breakdowns, health) live on the full Horizon
 * dashboard, linked from here.
 */
export default class HorizonQueueWidget extends QueueWidget {
    className(): string;
    /**
     * The horizon enrichment block from the loaded stats, or undefined.
     */
    horizonBlock(): HorizonBlock | undefined;
    titleItems(): ItemList<Mithril.Children>;
    headerActions(): ItemList<Mithril.Children>;
    tiles(): ItemList<Mithril.Children>;
    /**
     * Horizon tile keys resolve against horizon's own locale namespace; the
     * inherited pending/reserved/failed keys fall back to core's.
     */
    tileLabel(key: string): Mithril.Children;
    formatWait(maxWait: HorizonBlock['maxWait']): Mithril.Children;
}
export {};
