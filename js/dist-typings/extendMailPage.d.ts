/**
 * Surface the email-concurrency control on core's Mail admin page as well as on
 * the Horizon page. "How many emails send at once" is something an admin looks
 * for under Email first, so we implant the same setting there — bound to the
 * same `fof-horizon.email_concurrency` setting, and honouring the same lock
 * state (disabled + explained when pinned by env / config.php / extend.php).
 *
 * Only shown when Horizon is actually driving the queue; on the database queue
 * this setting has no effect, so it would be misleading.
 */
export default function extendMailPage(): void;
