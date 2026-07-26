import app from 'flarum/admin/app';
import DashboardWidget, { IDashboardWidgetAttrs } from 'flarum/admin/components/DashboardWidget';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Tooltip from 'flarum/common/components/Tooltip';
import Icon from 'flarum/common/components/Icon';
import type Mithril from 'mithril';
import statsStore from '../statsStore';
import { statTile } from '../statsView';

const trans = (key: string, params = {}) => app.translator.trans(`fof-horizon.admin.stats.${key}`, params);

export default class HorizonRedisWidget extends DashboardWidget {
  oncreate(vnode: Mithril.VnodeDOM<IDashboardWidgetAttrs, this>) {
    super.oncreate(vnode);
    statsStore.attach();
  }

  onremove() {
    statsStore.detach();
  }

  className() {
    return 'HorizonWidget HorizonWidget--redis';
  }

  content() {
    const { data, error } = statsStore;
    const redis = data?.redis;

    // Eviction policies like allkeys-lru are a perfectly reasonable choice
    // for a Flarum cache store. Only warn when the policy can evict AND the
    // server is actually approaching its memory limit.
    const underPressure = redis && redis.eviction_policy !== 'noeviction' && redis.memory_percentage !== null && redis.memory_percentage > 75;

    // The store type and version are already exposed to the admin frontend
    // by AdminContent (the status widget uses the same data) — so the
    // heading says what actually runs, e.g. "Valkey 9.0.1", not "Redis".
    const storeType: string = app.data.cacheStore || '';
    const storeVersion: string = app.data.cacheVersion || '';
    const serverTitle = storeType ? storeType.charAt(0).toUpperCase() + storeType.slice(1) : trans('kv_heading');

    return (
      <div className="HorizonWidget-body">
        <div className="HorizonWidget-header">
          <h3 className="HorizonWidget-title">
            <Icon name="fas fa-database" /> {serverTitle}
            {storeVersion && <span className="HorizonWidget-version">{storeVersion}</span>}
            {underPressure && (
              <Tooltip text={trans('eviction_warning', { policy: redis.eviction_policy, usage: redis.memory_percentage })}>
                <span className="HorizonWidget-pill HorizonWidget-pill--warning">
                  <Icon name="fas fa-exclamation-triangle" /> {trans('eviction_pressure')}
                </span>
              </Tooltip>
            )}
          </h3>
        </div>

        {error && <div className="HorizonWidget-error">{error}</div>}
        {!error && !redis && <LoadingIndicator />}
        {!error && redis && (
          <div className="HorizonWidget-tiles">
            {statTile('memory-used', redis.memory_used, redis.memory_percentage !== null ? `${redis.memory_percentage}%` : undefined)}
            {statTile('memory-peak', redis.memory_peak)}
            {statTile('memory-max', redis.memory_max)}
            <Tooltip text={trans('eviction_policy_tooltip')}>
              <a
                className="HorizonWidget-tile HorizonWidget-tile--eviction-policy"
                href="https://redis.io/docs/latest/develop/reference/eviction/"
                target="_blank"
                rel="noopener noreferrer"
              >
                <div className="HorizonWidget-tileLabel">{trans('data.eviction-policy')}</div>
                <div className="HorizonWidget-tileValue">{redis.eviction_policy}</div>
              </a>
            </Tooltip>
            {statTile('ops-per-sec', redis.ops_per_sec)}
            {statTile('connected-clients', redis.connected_clients)}
            {statTile('blocked-clients', redis.blocked_clients)}
          </div>
        )}
      </div>
    );
  }
}
