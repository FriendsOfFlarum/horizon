import app from 'flarum/admin/app';

export interface HealthFactor {
  key: string;
  impact: number;
  value: string;
}

/**
 * Shared state for the dashboard widgets: one fetch/poll loop feeding both
 * the queue card and the redis card. Widgets attach() on creation and
 * detach() on removal; polling only runs while at least one is mounted.
 */
class StatsStore {
  data: any = null;
  loading = false;
  error: string | null = null;
  lastRefresh?: number;
  autoRefresh = localStorage.getItem('horizonAutoRefresh') === 'true';
  refreshInterval = parseInt(localStorage.getItem('horizonRefreshInterval') || '5000', 10);

  private timer?: number;
  private consumers = 0;

  attach(): void {
    this.consumers++;

    if (this.consumers === 1) {
      this.load();

      if (this.autoRefresh) {
        this.start();
      }
    }
  }

  detach(): void {
    this.consumers = Math.max(0, this.consumers - 1);

    if (this.consumers === 0) {
      this.stop();
    }
  }

  async load(): Promise<void> {
    this.loading = true;
    this.error = null;
    m.redraw();

    try {
      this.data = await app.request({
        method: 'GET',
        url: app.forum.attribute('adminUrl') + '/horizon/api/stats',
      });
      this.lastRefresh = Date.now();
    } catch (error: any) {
      this.error = error?.response?.error || app.translator.trans('fof-horizon.admin.stats.error.fetch_failed');
      this.stop();
    }

    this.loading = false;
    m.redraw();
  }

  toggleAutoRefresh(enabled: boolean): void {
    this.autoRefresh = enabled;
    localStorage.setItem('horizonAutoRefresh', enabled ? 'true' : 'false');

    enabled ? this.start() : this.stop();
  }

  setRefreshInterval(ms: number): void {
    this.refreshInterval = ms;
    localStorage.setItem('horizonRefreshInterval', ms.toString());

    if (this.autoRefresh) {
      this.start();
    }
  }

  private start(): void {
    this.stop();
    this.timer = setInterval(() => this.load(), this.refreshInterval) as unknown as number;
  }

  private stop(): void {
    if (this.timer) {
      clearInterval(this.timer);
      this.timer = undefined;
    }
  }
}

export default new StatsStore();
