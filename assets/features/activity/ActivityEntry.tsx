import { Avatar, Badge } from '@nubitio/ui';

import { activityIcon, formatTime, type ActivityItem } from './activityFormat';

/**
 * One line of the activity record: who (or what kind of event), what happened, and when.
 * ERP-specific on purpose: the library's Timeline draws a connected stepper for process states, not a
 * flat log, and its items cannot carry the test ids and event type this list exposes.
 */
export const ActivityEntry = ({ entry }: { entry: ActivityItem }) => (
  <li className="activity-entry" data-testid="activity-row" data-type={entry.type}>
    {entry.actor ? (
      <Avatar owner={entry.actor} size={32} />
    ) : (
      <span
        className={`activity-entry__icon${entry.sensitive ? ' activity-entry__icon--alert' : ''}`}
        aria-hidden="true"
      >
        <i className={activityIcon(entry.type)} />
      </span>
    )}
    <div className="activity-entry__body">
      <span className="activity-entry__summary">{entry.summary}</span>
      {entry.detail ? <span className="activity-entry__detail">{entry.detail}</span> : null}
    </div>
    {entry.sensitive ? (
      <Badge pill variant="danger">
        Sensible
      </Badge>
    ) : null}
    <time className="activity-entry__time" dateTime={entry.occurredAt}>
      {formatTime(entry.occurredAt)}
    </time>
  </li>
);
