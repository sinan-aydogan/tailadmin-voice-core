<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $documentationTitle }}</title>
    <link rel="stylesheet" type="text/css" href="{{ asset('vendor/swagger-ui/swagger-ui.css') }}">
    <link rel="icon" type="image/png" href="{{ asset('vendor/swagger-ui/favicon-32x32.png') }}" sizes="32x32"/>
    <link rel="icon" type="image/png" href="{{ asset('vendor/swagger-ui/favicon-16x16.png') }}" sizes="16x16"/>
    <style>
        html {
            box-sizing: border-box;
            overflow-y: scroll;
            background-color: #0f0f11;
        }
        *, *:before, *:after {
            box-sizing: inherit;
        }
        body {
            margin: 0;
            background-color: #0f0f11;
            color: #e2e8f0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* TailAdmin Voice Core Dark Theme */
        body#dark-mode,
        #dark-mode .swagger-ui {
            background-color: #0f0f11;
            color: #cbd5e1;
        }
        #dark-mode .swagger-ui .topbar {
            background-color: #14151a;
            border-bottom: 1px solid #232630;
        }
        #dark-mode .swagger-ui .info {
            margin: 25px 0;
        }
        #dark-mode .swagger-ui .info .title {
            color: #f8fafc;
            font-size: 28px;
            font-weight: 700;
        }
        #dark-mode .swagger-ui .info p,
        #dark-mode .swagger-ui .info li,
        #dark-mode .swagger-ui .info table {
            color: #94a3b8;
        }
        #dark-mode .swagger-ui .info a {
            color: #f59e0b;
        }
        #dark-mode .swagger-ui .scheme-container {
            background: #14151a;
            border: 1px solid #232630;
            border-radius: 12px;
            padding: 18px 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
            margin-bottom: 24px;
        }
        #dark-mode .swagger-ui .schemes > label {
            color: #94a3b8;
            font-weight: 600;
        }
        #dark-mode .swagger-ui select,
        #dark-mode .swagger-ui input[type=text],
        #dark-mode .swagger-ui input[type=password],
        #dark-mode .swagger-ui input[type=search],
        #dark-mode .swagger-ui textarea {
            background-color: #1a1d25;
            color: #f1f5f9;
            border: 1px solid #333846;
            border-radius: 8px;
            padding: 8px 12px;
        }
        #dark-mode .swagger-ui select:focus,
        #dark-mode .swagger-ui input:focus,
        #dark-mode .swagger-ui textarea:focus {
            border-color: #f59e0b;
            outline: none;
        }
        #dark-mode .swagger-ui .btn {
            border-radius: 8px;
            color: #e2e8f0;
            border-color: #333846;
            background: #1a1d25;
            transition: all 0.15s ease;
        }
        #dark-mode .swagger-ui .btn:hover {
            background: #242934;
            color: #f8fafc;
        }
        #dark-mode .swagger-ui .btn.authorize {
            background: rgba(16, 185, 129, 0.15);
            border-color: rgba(16, 185, 129, 0.4);
            color: #34d399;
        }
        #dark-mode .swagger-ui .btn.authorize:hover {
            background: rgba(16, 185, 129, 0.25);
        }
        #dark-mode .swagger-ui .btn.authorize svg {
            fill: #34d399;
        }
        #dark-mode .swagger-ui .btn.cancel {
            border-color: #ef4444;
            color: #ef4444;
        }
        #dark-mode .swagger-ui .btn.execute {
            background-color: #f59e0b;
            color: #0f172a;
            border-color: #f59e0b;
            font-weight: 600;
        }
        #dark-mode .swagger-ui .btn.execute:hover {
            background-color: #d97706;
        }

        /* Operation Blocks */
        #dark-mode .swagger-ui .opblock-tag {
            color: #f8fafc;
            border-bottom: 1px solid #232630;
            font-size: 18px;
            padding: 14px 0;
        }
        #dark-mode .swagger-ui .opblock-tag small {
            color: #64748b;
        }
        #dark-mode .swagger-ui .opblock-tag svg {
            fill: #94a3b8;
        }
        #dark-mode .swagger-ui .opblock {
            border-radius: 10px;
            margin: 0 0 14px;
            border: 1px solid #232630;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
        #dark-mode .swagger-ui .opblock .opblock-summary {
            padding: 10px 14px;
        }
        #dark-mode .swagger-ui .opblock .opblock-summary-method {
            border-radius: 6px;
            font-weight: 700;
        }
        #dark-mode .swagger-ui .opblock .opblock-summary-path,
        #dark-mode .swagger-ui .opblock .opblock-summary-path__deprecated {
            color: #e2e8f0;
            font-weight: 600;
        }
        #dark-mode .swagger-ui .opblock .opblock-summary-description {
            color: #94a3b8;
        }
        #dark-mode .swagger-ui .opblock-body {
            background: #14161c;
        }
        #dark-mode .swagger-ui .opblock-section-header {
            background: #181b22;
            box-shadow: none;
            border-top: 1px solid #232630;
            border-bottom: 1px solid #232630;
        }
        #dark-mode .swagger-ui .opblock-section-header h4 {
            color: #cbd5e1;
        }

        /* Methods Coloring in Dark Mode */
        #dark-mode .swagger-ui .opblock.opblock-get {
            background: rgba(59, 130, 246, 0.08);
            border-color: rgba(59, 130, 246, 0.3);
        }
        #dark-mode .swagger-ui .opblock.opblock-get .opblock-summary-method {
            background: #2563eb;
        }
        #dark-mode .swagger-ui .opblock.opblock-post {
            background: rgba(16, 185, 129, 0.08);
            border-color: rgba(16, 185, 129, 0.3);
        }
        #dark-mode .swagger-ui .opblock.opblock-post .opblock-summary-method {
            background: #059669;
        }
        #dark-mode .swagger-ui .opblock.opblock-put {
            background: rgba(245, 158, 11, 0.08);
            border-color: rgba(245, 158, 11, 0.3);
        }
        #dark-mode .swagger-ui .opblock.opblock-put .opblock-summary-method {
            background: #d97706;
        }
        #dark-mode .swagger-ui .opblock.opblock-delete {
            background: rgba(239, 68, 68, 0.08);
            border-color: rgba(239, 68, 68, 0.3);
        }
        #dark-mode .swagger-ui .opblock.opblock-delete .opblock-summary-method {
            background: #dc2626;
        }

        /* Parameters, Tables & Responses */
        #dark-mode .swagger-ui table thead tr td,
        #dark-mode .swagger-ui table thead tr th {
            color: #94a3b8;
            border-bottom: 1px solid #232630;
        }
        #dark-mode .swagger-ui .parameter__name {
            color: #f1f5f9;
            font-weight: 600;
        }
        #dark-mode .swagger-ui .parameter__type {
            color: #f59e0b;
        }
        #dark-mode .swagger-ui .parameter__in {
            color: #64748b;
        }
        #dark-mode .swagger-ui .response-col_status {
            color: #f1f5f9;
        }
        #dark-mode .swagger-ui .response-col_description {
            color: #94a3b8;
        }
        #dark-mode .swagger-ui .responses-inner h4,
        #dark-mode .swagger-ui .responses-inner h5 {
            color: #cbd5e1;
        }
        #dark-mode .swagger-ui .highlight-code pre,
        #dark-mode .swagger-ui .microlight {
            background: #0b0c0e !important;
            color: #e2e8f0 !important;
            border: 1px solid #232630;
            border-radius: 8px;
        }
        #dark-mode .swagger-ui .model-box {
            background: #14161c;
        }
        #dark-mode .swagger-ui .model,
        #dark-mode .swagger-ui .model-title {
            color: #cbd5e1;
        }
        #dark-mode .swagger-ui section.models {
            border: 1px solid #232630;
            border-radius: 12px;
            background: #14151a;
        }
        #dark-mode .swagger-ui section.models h4 {
            color: #f8fafc;
            border-bottom: 1px solid #232630;
        }
        #dark-mode .swagger-ui .filter .operation-filter-input {
            border: 1px solid #333846;
            background: #14151a;
            color: #f8fafc;
            border-radius: 8px;
            padding: 10px 14px;
        }
        #dark-mode .swagger-ui .dialog-ux .modal-ux {
            background: #181b22;
            border: 1px solid #333846;
            color: #f8fafc;
            border-radius: 14px;
        }
        #dark-mode .swagger-ui .dialog-ux .modal-ux-header {
            border-bottom: 1px solid #232630;
        }
        #dark-mode .swagger-ui .dialog-ux .modal-ux-header h3 {
            color: #f8fafc;
        }
        #dark-mode .swagger-ui .dialog-ux .modal-ux-content h4 {
            color: #e2e8f0;
        }
    </style>
</head>

<body @if(config('l5-swagger.defaults.ui.display.dark_mode')) id="dark-mode" @endif>
<div id="swagger-ui">
    <div id="swagger-loader" style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:500px;color:#94a3b8;font-family:sans-serif;">
        <div style="width:40px;height:40px;border:3px solid rgba(255,255,255,0.1);border-top-color:#f59e0b;border-radius:50%;animation:spin 0.8s linear infinite;margin-bottom:16px;"></div>
        <p style="font-size:14px;font-weight:600;color:#cbd5e1;margin:0 0 6px 0;">TailAdmin Voice Core API Dokümantasyonu Yükleniyor...</p>
        <p style="font-size:12px;color:#64748b;margin:0;">Etkileşimli referans ve OpenAPI şeması hazırlanıyor</p>
    </div>
</div>

<script src="{{ file_exists(public_path('vendor/swagger-ui/swagger-ui-bundle.js')) ? asset('vendor/swagger-ui/swagger-ui-bundle.js') . '?v=3' : l5_swagger_asset($documentation, 'swagger-ui-bundle.js') . '&cb=v3' }}" charset="utf-8"></script>
<script src="{{ file_exists(public_path('vendor/swagger-ui/swagger-ui-standalone-preset.js')) ? asset('vendor/swagger-ui/swagger-ui-standalone-preset.js') . '?v=3' : l5_swagger_asset($documentation, 'swagger-ui-standalone-preset.js') . '&cb=v3' }}" charset="utf-8"></script>
<script>
    window.onload = function() {
        if (typeof SwaggerUIBundle === 'undefined') {
            const container = document.getElementById('swagger-ui');
            if (container) {
                container.innerHTML = `
                    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:500px;color:#e2e8f0;font-family:sans-serif;padding:2rem;text-align:center;">
                        <div style="background:#181b22;border:1px solid #333846;border-radius:16px;padding:36px;max-width:520px;box-shadow:0 10px 30px rgba(0,0,0,0.5);">
                            <div style="width:52px;height:52px;border-radius:14px;background:rgba(239,68,68,0.15);color:#f87171;display:flex;align-items:center;justify-content:center;margin:0 auto 18px auto;font-size:26px;">⚠️</div>
                            <h3 style="font-size:18px;font-weight:700;margin-bottom:8px;color:#f8fafc;">Dokümantasyon Yüklenemedi</h3>
                            <p style="font-size:13px;color:#94a3b8;line-height:1.6;margin-bottom:22px;">Swagger UI kütüphanesi tarayıcı tarafından yüklenirken bir sorun oluştu. Lütfen sayfayı önbelleği temizleyerek yenileyin.</p>
                            <button onclick="window.location.reload(true)" style="background:#f59e0b;color:#0f172a;font-weight:700;font-size:13px;padding:10px 22px;border-radius:10px;border:none;cursor:pointer;">Sayfayı Yenile</button>
                        </div>
                    </div>
                `;
            }
            return;
        }

        const urls = [];

        @foreach($urlsToDocs as $title => $url)
            urls.push({name: "{{ $title }}", url: "{{ $url }}"});
        @endforeach

        // Build a system
        const ui = SwaggerUIBundle({
            dom_id: '#swagger-ui',
            urls: urls,
            "urls.primaryName": "{{ $documentationTitle }}",
            operationsSorter: {!! isset($operationsSorter) ? '"' . $operationsSorter . '"' : 'null' !!},
            configUrl: {!! isset($configUrl) ? '"' . $configUrl . '"' : 'null' !!},
            validatorUrl: {!! isset($validatorUrl) ? '"' . $validatorUrl . '"' : 'null' !!},
            oauth2RedirectUrl: "{{ route('l5-swagger.'.$documentation.'.oauth2_callback', [], $useAbsolutePath) }}",

            requestInterceptor: function(request) {
                request.headers['X-CSRF-TOKEN'] = '{{ csrf_token() }}';
                return request;
            },

            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIStandalonePreset
            ],

            plugins: [
                SwaggerUIBundle.plugins.DownloadUrl
            ],

            layout: "StandaloneLayout",
            docExpansion : "{!! config('l5-swagger.defaults.ui.display.doc_expansion', 'none') !!}",
            deepLinking: true,
            filter: {!! config('l5-swagger.defaults.ui.display.filter') ? 'true' : 'false' !!},
            persistAuthorization: "{!! config('l5-swagger.defaults.ui.authorization.persist_authorization') ? 'true' : 'false' !!}",
        });

        window.ui = ui;

        @if(in_array('oauth2', array_column(config('l5-swagger.defaults.securityDefinitions.securitySchemes'), 'type')))
        ui.initOAuth({
            usePkceWithAuthorizationCodeGrant: "{!! (bool)config('l5-swagger.defaults.ui.authorization.oauth2.use_pkce_with_authorization_code_grant') !!}"
        });
        @endif
    };
</script>
</body>
</html>
