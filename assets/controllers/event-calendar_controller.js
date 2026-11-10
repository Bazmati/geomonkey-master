import { Controller } from '@hotwired/stimulus';

/*
 * Contrôleur Stimulus : calendrier des événements (desktop).
 *
 * FullCalendar est chargé en bundles GLOBAUX auto-hébergés
 * (assets/vendor/fullcalendar/*.global.min.js, référencés dans front/base.html.twig) :
 * ils exposent window.FullCalendar (Calendar, DayGrid). C'est l'approche de la doc
 * officielle FullCalendar, adaptée à un projet sans CDN.
 *
 * La locale française est inline : le fichier locales/fr.global.min.js n'existe
 * pas sur le CDN et l'importmap ESM ne résout pas les sous-chemins.
 */

/* Locale française inline (FullCalendar accepte un objet de locale) */
const frLocale = {
    code: 'fr',
    week: { dow: 1, doy: 4 },
    buttonText: {
        prev: '‹',
        next: '›',
        today: "Aujourd'hui",
        month: 'Mois',
        week: 'Semaine',
    },
    weekText: 'Sm',
    allDayText: 'Journée',
    noEventsText: 'Aucun événement à afficher',
    dayNames: ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'],
    dayNamesShort: ['dim', 'lun', 'mar', 'mer', 'jeu', 'ven', 'sam'],
    monthNames: ['janvier', 'février', 'mars', 'avril', 'mai', 'juin',
                 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
    monthNamesShort: ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin',
                      'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'],
};

export default class extends Controller {
    static values = {
        feedUrl: String,
    };

    connect() {
        this.calendar = new window.FullCalendar.Calendar(this.element, {
            plugins: [window.FullCalendar.DayGrid],
            locale: frLocale,
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
