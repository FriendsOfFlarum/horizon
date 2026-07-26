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
declare class StatsStore {
    data: any;
    loading: boolean;
    error: string | null;
    lastRefresh?: number;
    autoRefresh: boolean;
    refreshInterval: number;
    private timer?;
    private consumers;
    attach(): void;
    detach(): void;
    load(): Promise<void>;
    toggleAutoRefresh(enabled: boolean): void;
    setRefreshInterval(ms: number): void;
    private start;
    private stop;
}
declare const _default: StatsStore;
export default _default;
