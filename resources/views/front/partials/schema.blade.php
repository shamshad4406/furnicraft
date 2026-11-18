{{--
    One JSON-LD block.

    The HEX flags are not optional. @json passes whatever flags it is given
    straight to json_encode, replacing Laravel's defaults rather than adding
    to them — so naming only UNESCAPED_SLASHES here would drop JSON_HEX_TAG
    and let a "<" in a product name, a brand or a review close this very
    script tag. That is stored XSS on every visitor's product page, written
    by whoever can name a product.

    With JSON_HEX_TAG a "<" becomes <, which is still valid JSON and
    still read correctly by search engines.
--}}
<script type="application/ld+json">@json($data,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
