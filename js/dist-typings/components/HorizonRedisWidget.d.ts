import DashboardWidget, { IDashboardWidgetAttrs } from 'flarum/admin/components/DashboardWidget';
import type Mithril from 'mithril';
export default class HorizonRedisWidget extends DashboardWidget {
    oncreate(vnode: Mithril.VnodeDOM<IDashboardWidgetAttrs, this>): void;
    onremove(): void;
    className(): string;
    content(): JSX.Element;
}
