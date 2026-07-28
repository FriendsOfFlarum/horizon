import app from 'flarum/admin/app';
import QueueWidget, { type QueueStats, type QueueTotals } from 'flarum/admin/components/QueueWidget';
import ItemList from 'flarum/common/utils/ItemList';
import LinkButton from 'flarum/common/components/LinkButton';
import type Mithril from 'mithril';

const trans = (key: string, params = {}) => app.translator.trans(`fof-horizon.admin.stats.${key}`, params);

/**
 * The horizon enrichment block carried inside `/queue/stats` totals, produced
 * by HorizonQueueStatsProvider on the backend. Optional so the widget degrades
 * gracefully if an older backend (no enrichment) responds.
 */
interface HorizonBlock {
  processes: number;
  supervisors: number;
  jobsPerMinute: number;
  maxWait: { queue: string; seconds: number } | null;
  paused: boolean;
}

type HorizonTotals = QueueTotals & { horizon?: HorizonBlock };

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
  className() {
    return super.className() + ' HorizonQueueWidget';
  }

  /**
   * The horizon enrichment block from the loaded stats, or undefined.
   */
  horizonBlock(): HorizonBlock | undefined {
    return (this.stats as (QueueStats & { totals: HorizonTotals }) | null)?.totals.horizon;
  }

  titleItems(): ItemList<Mithril.Children> {
    const items = super.titleItems();
    const horizon = this.horizonBlock();

    if (horizon) {
      // A single at-a-glance status pill: paused shows the pending backlog
      // (the number that matters when jobs aren't flowing); otherwise running.
      const pending = this.stats?.totals.pending ?? 0;
      const label =
        horizon.paused && pending ? trans('status.paused_pending', { count: pending }) : trans(`status.${horizon.paused ? 'paused' : 'running'}`);

      items.add(
        'horizon-status',
        <span className={`HorizonWidget-pill HorizonWidget-pill--${horizon.paused ? 'paused' : 'running'}`}>{label}</span>,
        100
      );
    }

    return items;
  }

  headerActions(): ItemList<Mithril.Children> {
    const items = super.headerActions();

    // Link to the full Horizon dashboard for deep metrics (throughput history,
    // per-queue breakdowns, health, batches) that don't belong on the card.
    items.add(
      'horizon-dashboard',
      <LinkButton
        className="Button Button--icon Button--flat"
        icon="fas fa-external-link-alt"
        href={app.forum.attribute('adminUrl') + '/horizon'}
        external={true}
        target="_blank"
        aria-label={trans('full_dashboard')}
        title={trans('full_dashboard')}
      />,
      90 // after the inherited refresh button (priority 100)
    );

    return items;
  }

  tiles(): ItemList<Mithril.Children> {
    const items = super.tiles();
    const horizon = this.horizonBlock();

    if (!horizon) {
      return items;
    }

    // Lower priorities than core's pending(100)/reserved(90)/failed(80) so the
    // horizon tiles sit after them. Reuses core's tile() — the value can be a
    // string now, and tileLabel() below points label lookups at horizon's
    // locale namespace.
    items.add('horizon-processes', this.tile('processes', horizon.processes), 70);
    items.add('horizon-jobs-per-minute', this.tile('jobs_per_minute', horizon.jobsPerMinute), 60);
    items.add('horizon-max-wait', this.tile('max_wait', this.formatWait(horizon.maxWait)), 50);
    items.add(
      'horizon-status',
      this.tile('status', trans(horizon.paused ? 'status.paused' : 'status.running'), horizon.paused ? 'QueueWidget-tile--alert' : ''),
      40
    );

    return items;
  }

  /**
   * Horizon tile keys resolve against horizon's own locale namespace; the
   * inherited pending/reserved/failed keys fall back to core's.
   */
  tileLabel(key: string): Mithril.Children {
    const horizonKeys = ['processes', 'jobs_per_minute', 'max_wait', 'status'];

    return horizonKeys.includes(key) ? trans('tile.' + key) : super.tileLabel(key);
  }

  formatWait(maxWait: HorizonBlock['maxWait']): Mithril.Children {
    if (!maxWait || maxWait.seconds <= 0) {
      return trans('horizon_max_wait_none');
    }

    return trans('horizon_max_wait_value', { seconds: maxWait.seconds, queue: maxWait.queue });
  }
}
