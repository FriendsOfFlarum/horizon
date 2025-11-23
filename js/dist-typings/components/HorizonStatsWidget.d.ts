import DashboardWidget, { IDashboardWidgetAttrs } from 'flarum/admin/components/DashboardWidget';
import Mithril from 'mithril';
import ItemList from 'flarum/common/utils/ItemList';
export default class HorizonStatsWidget extends DashboardWidget {
    loading: boolean;
    data: any;
    autoRefreshEnabled: boolean;
    autoRefreshInterval?: number;
    oncreate(vnode: Mithril.Vnode<IDashboardWidgetAttrs>): void;
    onremove(): void;
    loadHorizonStats(): Promise<void>;
    toggleAutoRefresh(enabled: boolean): void;
    setAutoRefresh(): void;
    clearAutoRefresh(): void;
    className(): string;
    content(): JSX.Element;
    renderStatsSection(): JSX.Element;
    renderStat(label: Mithril.Children, value: string, infoLabel?: Mithril.Children | undefined, infoUrl?: string | undefined): JSX.Element;
    statItems(label: Mithril.Children, value: string, infoLabel: Mithril.Children | undefined, infoUrl: string | undefined): ItemList<Mithril.Children>;
    labelItems(label: Mithril.Children, infoLabel: Mithril.Children | undefined, infoUrl: string | undefined): ItemList<Mithril.Children>;
    renderStatusIndicator(status: string | null): JSX.Element;
}
