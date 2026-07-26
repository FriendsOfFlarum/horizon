import type Mithril from 'mithril';
/**
 * Human label for a trim window given in minutes, e.g. 60 → "past hour",
 * 10080 → "past 7 days".
 */
export declare function periodLabel(minutes?: number): Mithril.Children;
/**
 * A single labeled stat tile. The label comes from the locale by key; the
 * optional sub line carries context such as the count's window or a queue
 * name.
 */
export declare function statTile(key: string, value: Mithril.Children, sub?: Mithril.Children, href?: string): Mithril.Children;
/**
 * URL of a page within the full Horizon dashboard, e.g. horizonUrl('/failed').
 */
export declare function horizonUrl(path: string): string;
