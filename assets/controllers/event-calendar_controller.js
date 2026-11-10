import { Controller } from '@hotwired/stimulus';
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import frLocale from '@fullcalendar/core/locales.fr';

/*
 * Contrôleur Stimulus : calendrier des événements (desktop)
 *
 * Usage : <div data-controller="event-calendar" data-event-calendar-feed-url-value="{{ path('app_event_data') }}"></div>
 * Le feed JSON renvoie {id, title, start, end, url, membersOnly, location}.
 */
export default class extends Controller {
    static values = {
        feedUrl: String,
    };

    connect() {
        this.calendar = new Calendar(this.element, {
            plugins: [dayGridPlugin],
            locale: frLocale,
            initialView: 'dayGridMonth',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,dayGridWeek',
            },
            events: this.feedUrlValue,
            firstDay: 1, // lundi
            displayEventTime: true,
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false,
            },
            // Clic sur un événement → page publique de l'événement
            eventClick: (info) => {
                info.jsEvent.preventDefault();
                if (info.event.url) {
                    window.location.assign(info.event.url);
                }
            },
            // Classe additionnelle pour les événements réservés aux membres
            eventClassNames: (info) =>
                info.event.extendedProps.membersOnly ? ['gm-event-members'] : [],
        });

        this.calendar.render();
    }

    disconnect() {
        if (this.calendar) {
            this.calendar.destroy();
            this.calendar = null;
        }
    }
}
