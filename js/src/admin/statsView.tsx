import app from 'flarum/admin/app';
import type Mithril from 'mithril';

/**
 * Human label for a trim window given in minutes, e.g. 60 → "past hour",
 * 10080 → "past 7 days".
 */
export function periodLabel(minutes?: number): Mithril.Children {
  if (!minutes) return undefined;

  if (minutes % 1440 === 0) {
    const days = minutes / 1440;
    return days === 1
      ? app.translator.trans('fof-horizon.admin.stats.period.day')
      : app.translator.trans('fof-horizon.admin.stats.period.days', { count: days });
  }

  if (minutes % 60 === 0) {
    const hours = minutes / 60;
    return hours === 1
      ? app.translator.trans('fof-horizon.admin.stats.period.hour')
      : app.translator.trans('fof-horizon.admin.stats.period.hours', { count: hours });
  }

  return app.translator.trans('fof-horizon.admin.stats.period.minutes', { count: minutes });
}

/**
 * A single labeled stat tile. The label comes from the locale by key; the
 * optional sub line carries context such as the count's window or a queue
 * name.
 */
export function statTile(key: string, value: Mithril.Children, sub?: Mithril.Children, href?: string): Mithril.Children {
  const content = [
    <div className="HorizonWidget-tileLabel">{app.translator.trans(`fof-horizon.admin.stats.data.${key}`)}</div>,
    <div className="HorizonWidget-tileValue">{value ?? '0'}</div>,
    sub && <div className="HorizonWidget-tileSub">{sub}</div>,
  ];

  const className = `HorizonWidget-tile HorizonWidget-tile--${key}`;

  return href ? (
    <a className={className} href={href} target="_blank" rel="noopener noreferrer">
      {content}
    </a>
  ) : (
    <div className={className}>{content}</div>
  );
}

/**
 * URL of a page within the full Horizon dashboard, e.g. horizonUrl('/failed').
 */
export function horizonUrl(path: string): string {
  return app.forum.attribute('adminUrl') + '/horizon' + path;
}
