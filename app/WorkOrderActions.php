<?php

declare(strict_types=1);

namespace App;

use App\Models\WorkOrder;
use Throwable;

/**
 * The three verbs a mechanic uses on a work order — pick it up, finish it,
 * hand it back — plus the manager's exact status control.
 *
 * These same buttons appear on the work order itself, on the work-order list
 * and on both dashboards, so the rules live here once rather than three times.
 * It is a class the pages include, not a front controller: every page in this
 * application owns its own POST, and a shared endpoint that redirected to a
 * URL from the request would be an open redirect on an authenticated action.
 *
 * The calling page has already run Csrf::verify(). Each action re-checks its
 * own permission here, whatever the buttons on the page did or did not show.
 */
final class WorkOrderActions
{
    /** The actions this class owns. Anything else is the page's own business. */
    public const ACTIONS = ['claim', 'hand_back', 'done', 'reopen', 'status'];

    private function __construct()
    {
    }

    /**
     * Run the posted action, if it is one of ours.
     *
     * @return bool true when it was handled — the caller then redirects
     */
    public static function handle(): bool
    {
        $action = Request::string('action');

        if (!in_array($action, self::ACTIONS, true)) {
            return false;
        }

        $workOrder = WorkOrder::find(Request::int('id'));

        if ($workOrder === null) {
            flash('error', 'That work order could not be found.');

            return true;
        }

        try {
            switch ($action) {
                case 'claim':
                    self::claim($workOrder);
                    break;
                case 'hand_back':
                    self::handBack($workOrder);
                    break;
                case 'done':
                    self::done($workOrder);
                    break;
                case 'reopen':
                    self::reopen($workOrder);
                    break;
                case 'status':
                    self::status($workOrder);
                    break;
            }
        } catch (Throwable $e) {
            log_error('Work order action "' . $action . '" failed: ' . $e->getMessage());
            flash('error', 'That could not be saved. The error has been recorded.');
        }

        return true;
    }

    /** @param array<string, mixed> $workOrder */
    private static function claim(array $workOrder): void
    {
        if (!Acl::canWorkOnWorkOrder($workOrder)) {
            abort(403, 'You cannot work on this work order.');
        }

        if (WorkOrder::claim((int) $workOrder['id'])) {
            flash('success', 'It is yours. ' . (string) $workOrder['wo_number'] . ' is on your jobs list now.');
        }
    }

    /** @param array<string, mixed> $workOrder */
    private static function handBack(array $workOrder): void
    {
        $mine = (int) ($workOrder['assigned_to'] ?? 0) === (int) Auth::id();

        if (!$mine && !can('workorders.assign')) {
            abort(403, 'That job has somebody else\'s name on it.');
        }

        if (WorkOrder::handBack((int) $workOrder['id'])) {
            flash('success', 'Handed back. ' . (string) $workOrder['wo_number'] . ' is on the open list again.');
        }
    }

    /**
     * "Done — it is fixed."
     *
     * Somebody who may not close still gets a useful outcome: what they typed
     * is saved on the record and the people who run the workshop are told the
     * job is finished and waiting for them. Nothing a mechanic types is ever
     * thrown away because of a permission.
     *
     * @param array<string, mixed> $workOrder
     */
    private static function done(array $workOrder): void
    {
        if (!Acl::canWorkOnWorkOrder($workOrder)) {
            abort(403, 'You cannot work on this work order.');
        }

        $id         = (int) $workOrder['id'];
        $resolution = Request::string('resolution');
        $downtime   = feature_on('downtime') ? Request::intOrNull('downtime_minutes') : null;

        if ($downtime !== null && $downtime < 0) {
            flash('error', 'Downtime cannot be less than zero.');

            return;
        }

        if (Acl::canCloseWorkOrder($workOrder)) {
            $done = WorkOrder::finish($id, [
                'resolution'      => $resolution,
                'downtime'        => $downtime,
                'back_in_service' => Request::bool('back_in_service'),
            ]);

            flash(
                'success',
                $done
                    ? (string) $workOrder['wo_number'] . ' is finished. Nice one.'
                    : 'That job was already finished.'
            );

            return;
        }

        // No permission to close: keep their words, hand the job over.
        WorkOrder::markFinishedPendingSignOff($id, $resolution, $downtime);

        flash('success', 'Thanks — ' . (string) $workOrder['wo_number']
            . ' is marked as finished and a manager has been asked to sign it off.');
    }

    /** @param array<string, mixed> $workOrder */
    private static function reopen(array $workOrder): void
    {
        if (!Acl::canWorkOnWorkOrder($workOrder) && !Acl::canEditWorkOrder($workOrder)) {
            abort(403, 'You cannot reopen this work order.');
        }

        if (!Status::isClosedWorkOrder((string) $workOrder['status'])) {
            return;
        }

        WorkOrder::update((int) $workOrder['id'], ['status' => 'in_progress']);
        flash('success', (string) $workOrder['wo_number'] . ' is open again.');
    }

    /**
     * The exact status control: every move Status::workOrderTransitions()
     * allows, for whoever is entitled to make it.
     *
     * @param array<string, mixed> $workOrder
     */
    private static function status(array $workOrder): void
    {
        if (!Acl::canWorkOnWorkOrder($workOrder)) {
            abort(403, 'You cannot change this work order.');
        }

        $id = (int) $workOrder['id'];
        // 'to' is the field the buttons post; 'status' is kept for the select,
        // for a second tab and for anything already bookmarked.
        $to = Request::string('to') !== '' ? Request::string('to') : Request::string('status');

        // "Leave it as it is" is a real answer: save the words and the
        // downtime, change nothing else.
        if ($to === '') {
            self::keepWhatTheyTyped($workOrder, true);
            flash('success', 'Saved.');

            return;
        }

        if (!Status::isValid($to, 'workorder')) {
            flash('error', 'That is not a status a work order can be in.');

            return;
        }

        if ($to === 'completed' && !Acl::canCloseWorkOrder($workOrder)) {
            self::done($workOrder);

            return;
        }

        if ($to === 'cancelled' && !Acl::canCancelWorkOrder($workOrder)) {
            self::keepWhatTheyTyped($workOrder);
            flash('error', 'Only somebody who assigns work can cancel a job. '
                . 'Anything you typed has been saved on the record.');

            return;
        }

        $update     = ['status' => $to];
        $resolution = Request::string('resolution');

        // A blank box clears the text: "leave it alone" is what not touching
        // the form does, so there has to be a way to remove a wrong sentence.
        if (Request::has('resolution')) {
            $update['resolution'] = $resolution === '' ? null : mb_substr($resolution, 0, 5000, 'UTF-8');
        }

        if (feature_on('downtime')) {
            $downtime = Request::intOrNull('downtime_minutes');

            if ($downtime !== null && $downtime < 0) {
                flash('error', 'Downtime cannot be less than zero.');

                return;
            }

            if ($downtime !== null) {
                $update['downtime_minutes'] = min($downtime, 525600);
            }
        }

        if (Status::isClosedWorkOrder($to)) {
            $update['back_in_service'] = Request::bool('back_in_service');
        }

        if ($to === 'completed') {
            WorkOrder::finish($id, [
                'resolution'      => $resolution,
                'downtime'        => $update['downtime_minutes'] ?? null,
                'back_in_service' => Request::bool('back_in_service'),
            ]);
        } else {
            unset($update['back_in_service']);
            WorkOrder::update($id, $update);
        }

        flash('success', 'Status updated.');
    }

    /**
     * Save the words and the downtime from a change that was refused, so a
     * refusal never costs somebody the paragraph they typed on a phone.
     *
     * @param array<string, mixed> $workOrder
     */
    private static function keepWhatTheyTyped(array $workOrder, bool $mayClear = false): void
    {
        $update     = [];
        $resolution = Request::string('resolution');

        if ($resolution !== '') {
            $update['resolution'] = mb_substr($resolution, 0, 5000, 'UTF-8');
        } elseif ($mayClear && Request::has('resolution')) {
            // Emptying the box on purpose removes the sentence; a refusal
            // elsewhere must never take words away that were not touched.
            $update['resolution'] = null;
        }

        if (feature_on('downtime')) {
            $downtime = Request::intOrNull('downtime_minutes');

            if ($downtime !== null && $downtime >= 0) {
                $update['downtime_minutes'] = min($downtime, 525600);
            }
        }

        if ($update !== []) {
            WorkOrder::update((int) $workOrder['id'], $update);
        }
    }
}
