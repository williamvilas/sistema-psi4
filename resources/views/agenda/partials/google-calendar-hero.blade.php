<div class="mb-4" id="google-calendar-hero">
    <x-integration-intro
        icon="fab fa-google"
        :title="__('agenda.google_hero_title')"
        :text="__('agenda.google_hero_text')"
        :note="__('agenda.google_hero_note')"
        :url="route('settings.google-calendar.connect', ['from' => \App\Http\Requests\GoogleCalendar\ConnectRequest::ORIGIN_AGENDA])"
        :button-label="__('agenda.google_hero_connect')"
        button-icon="far fa-calendar-check"
        gradient="linear-gradient(135deg, #4285f4 0%, #1a5fd0 100%)"
        :dismiss-label="__('agenda.google_hero_dismiss')"
        dismiss-action="dismissGoogleCalendarHero()"
    />
</div>
