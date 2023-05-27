<?php

namespace Saade\FilamentFullCalendar\Widgets\Concerns;

use Closure;
use Filament\Forms\ComponentContainer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Saade\FilamentFullCalendar\Widgets\Forms\CreateEventForm;
use Saade\FilamentFullCalendar\Widgets\Forms\EditEventForm;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

/**
 * @property ComponentContainer $createEventForm
 * @property ComponentContainer $editEventForm
 */
trait CanManageEvents
{
    use AuthorizesActions;
    use CanManageModals;
    use CreateEventForm;
    use EditEventForm;
    use EvaluateClosures;

    public int | string | null $event_id = null;
    public ?Model $event = null;

    protected function setUpForms(): void
    {
        if (static::canCreate()) {
            $this->createEventForm->fill();
        }

        if (static::canEdit()) {
            $this->editEventForm->fill();
        }

        if (static::canView()) {
            $this->editEventForm->fill();
        }
    }

    protected function getForms(): array
    {
        return array_merge(
            $this->getCreateEventForm(),
            $this->getEditEventForm()
        );
    }

    public function onEventClick($event): void
    {
        if (! static::canView($event)) {
            return;
        }

        $this->editEventForm
            ->disabled(! static::canEdit($event))
            ->fill($event);

        if (method_exists($this, 'resolveEventRecord')) {
            $this->event = $this->resolveEventRecord($event);
        } else {
            $this->event_id = $event['id'] ?? null;
        }

        $this->dispatchBrowserEvent('open-modal', ['id' => 'fullcalendar--edit-event-modal']);
    }

    public function onCreateEventClick(array $date): void
    {
        if (! static::canCreate()) {
            return;
        }

        $this->evaluate($this->handleCreateEventClickUsing(), [
            'date' => $date,
        ]);

        $this->dispatchBrowserEvent('open-modal', ['id' => 'fullcalendar--create-event-modal']);
    }

    protected function handleCreateEventClickUsing(): Closure
    {
        return function ($date, FullCalendarWidget $calendar) {
            $timezone = $this->config('timeZone') !== ' local'
                ? $this->config('timeZone', config('app.timezone'))
                : config('app.timezone');

            if (isset($date['date'])) { // for single date click
                $length = $this->config('slotDuration')
                    ? Carbon::createFromTimeString($this->config('slotDuration'))->format('i')
                    : null;
                
                $start = Carbon::parse($date['date'], $timezone);
                $end = $length ? Carbon::parse($date['date'], $timezone)->addMinutes($length) : $start->clone();
            } else { // for date range select
                $start = Carbon::parse($date['start'], $timezone);
                $end = Carbon::parse($date['end'], $timezone);
            }

            $calendar->createEventForm->fill([
                'start' => $start,
                'end' => $end,
                'allDay' => $date['allDay']
            ]);
        };
    }
}
