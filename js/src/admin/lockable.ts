import app from 'flarum/admin/app';

export type Lock = { source: 'env' | 'code'; value: unknown };

/**
 * If a setting is pinned by a higher-precedence layer (env / config.php /
 * extend.php), the admin field can't change the effective value — so disable
 * the input and explain why. `suffix` is the setting key without the
 * `fof-horizon.` prefix (e.g. `email_concurrency`, `trim.recent`).
 *
 * Returns the base setting config unchanged when the setting is not locked.
 */
export function lockable(suffix: string, base: Record<string, any>): Record<string, any> {
  const locks = (app.data.horizonLockedSettings ?? {}) as Record<string, Lock>;
  const lock = locks[suffix];

  if (!lock) return base;

  const reason = app.translator.trans(`fof-horizon.admin.settings.locked_by_${lock.source}`, { value: String(lock.value) });

  return {
    ...base,
    disabled: true,
    help: [base.help, reason].filter(Boolean),
  };
}
