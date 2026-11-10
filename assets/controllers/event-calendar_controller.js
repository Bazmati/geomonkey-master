import { Controller } from '@hotwired/stimulus';

/*
 * Contrôleur Stimulus : calendrier des événements (desktop).
 *
 * FullCalendar est chargé en bundles GLOBAUX auto-hébergés
 * (assets/vendor/fullcalendar/*.global.min.js, dans front/base.html.twig) :
 * window.FullCalendar expose Calendar et DayGrid.
 *
 * La locale française vient du fichier officiel locales-fr.global.min.js
 * (il alimente FullCalendar.globalLocales) — le contrôleur n'a plus qu'à
 * demander locale: 'fr'.
 */

export default class extends Controller {
    static values = {
        feedUrl: String,
    };

    connect() {
        this.calendar = new window.FullCalendar.Calendar(this.element, {
            plugins: [window.FullCalendar.DayGrid.default],
            locale: 'fr',
            initialView: 'dayGridMonth',
            height: 'auto',
            firstDay: 1, // lundi
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,dayGridWeek',
            },
            events: this.feedUrlValue,
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
