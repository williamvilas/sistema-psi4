@props(['tag'])

<script>
    window.addEventListener('load', function () {
        try {
            var tag = @json($tag);
            var storageKey = 'funnel_tags';
            var visitorKey = 'funnel_visitor_id';

            var sent = JSON.parse(localStorage.getItem(storageKey) || '[]');
            if (sent.indexOf(tag) !== -1) {
                return;
            }

            var visitorId = localStorage.getItem(visitorKey);
            if (!visitorId) {
                visitorId = crypto.randomUUID();
                localStorage.setItem(visitorKey, visitorId);
            }

            var params = new URLSearchParams(window.location.search);

            fetch(@json(route('funnel.track')), {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                keepalive: true,
                body: JSON.stringify({
                    visitor_id: visitorId,
                    tag: tag,
                    path: window.location.pathname,
                    referrer: document.referrer ? document.referrer.substring(0, 255) : null,
                    utm_source: params.get('utm_source'),
                    utm_medium: params.get('utm_medium'),
                    utm_campaign: params.get('utm_campaign')
                })
            }).then(function (response) {
                if (response.ok) {
                    sent.push(tag);
                    localStorage.setItem(storageKey, JSON.stringify(sent));
                }
            }).catch(function () {});
        } catch (e) {
            // Rastreamento nunca deve quebrar a página.
        }
    });
</script>
