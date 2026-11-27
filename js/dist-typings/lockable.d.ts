export type Lock = {
    source: 'env' | 'code';
    value: unknown;
};
/**
 * If a setting is pinned by a higher-precedence layer (env / config.php /
 * extend.php), the admin field can't change the effective value — so disable
 * the input and explain why. `suffix` is the setting key without the
 * `fof-horizon.` prefix (e.g. `email_concurrency`, `trim.recent`).
 *
 * Generic in the config shape so the caller's setting type is preserved (the
 * setting builders type-check their argument); returns the base config
 * unchanged when the setting is not locked, otherwise the same shape with
 * `disabled` set and the lock reason appended to `help`.
 */
export declare function lockable<T extends {
    help?: unknown;
}>(suffix: string, base: T): T;
