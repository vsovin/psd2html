<!-- chunks/meta.tpl — <head>: SEO-мета, OG, canonical, микроразметка Organization+WebSite, critical CSS.
     Шаблон base. TV: seo_title, seo_description, og_image. -->
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>[[*seo_title:is=`]:then=`[[++site_name]] — PSD в HTML5 онлайн`:else=`[[*seo_title]]`]]</title>
<meta name="description" content="[[*seo_description:default=`Бесплатный онлайн-конвертер PSD в HTML5: загрузите макет Photoshop и получите ZIP с semantic-вёрсткой.`]]">
<link rel="canonical" href="[[++site_url]][[~ID]]">

<!-- Open Graph / Twitter -->
<meta property="og:type" content="website">
<meta property="og:title" content="[[*seo_title:default=`[[++site_name]]`]]">
<meta property="og:description" content="[[*seo_description]]">
<meta property="og:url" content="[[++site_url]][[~ID]]">
<meta property="og:image" content="[[++site_url]][[*og_image]]">
<meta name="twitter:card" content="summary_large_image">

<!-- noindex для страниц результата (устанавливается на ресурсе result через TV/заглушку) -->
[[+noindex:eq=`1`:then=`<meta name="robots" content="noindex,follow">`]]

<!-- Bootstrap 5 (CDN c SRI; defer для PageSpeed) + стили компонента -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="stylesheet" href="/assets/components/psd2html/css/converter.css">

<!-- Critical CSS инлайном (первый экран конвертера) — см. docs/critical.css, сюда вставляется минифицированным -->
<style>.psd-drop{border:2px dashed #0d6efd;border-radius:.75rem;background:rgba(13,110,253,.04);padding:2.5rem 1rem;text-align:center;cursor:pointer}</style>

<!-- Микроразметка Organization + WebSite (sitename + поиск при появлении) -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "@id": "[[++site_url]]#org",
      "name": "[[++site_name]]",
      "url": "[[++site_url]]",
      "logo": "[[++site_url]]assets/img/logo.svg",
      "contactPoint": [{ "@type": "ContactPoint", "contactType": "customer support", "email": "support@example.com" }]
    },
    {
      "@type": "WebSite",
      "@id": "[[++site_url]]#website",
      "url": "[[++site_url]]",
      "name": "[[++site_name]]",
      "publisher": { "@id": "[[++site_url]]#org" }
    }
  ]
}
</script>
