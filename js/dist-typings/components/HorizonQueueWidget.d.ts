import DashboardWidget, { IDashboardWidgetAttrs } from 'flarum/admin/components/DashboardWidget';
import type Mithril from 'mithril';
import { HealthFactor } from '../statsStore';
export default class HorizonQueueWidget extends DashboardWidget {
    oncreate(vnode: Mithril.VnodeDOM<IDashboardWidgetAttrs, this>): void;
    onremove(): void;
    className(): string;
    content(): JSX.Element;
    tiles(data: any): JSX.Element;
    statusPill(status: string, pendingJobs?: number): JSX.Element;
    healthPill(health: {
        score: number;
        factors: HealthFactor[];
    }): JSX.Element | null;
}
