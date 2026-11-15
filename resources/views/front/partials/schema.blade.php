{{--
    One JSON-LD block.

    @json escapes the output, so a product name containing a quote cannot
    break out of the script tag — which is why the data is passed as an array
    rather than pre-encoded JSON.
--}}
<script type="application/ld+json">@json($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
