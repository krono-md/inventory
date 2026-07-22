<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>@yield('title', 'Nexora')</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
        <link rel="stylesheet" href="{{ asset('css/inventory.css') }}" />
        <script>if(localStorage.getItem('sidebarState')==='open'){document.documentElement.style.setProperty('--sidebar-width','250px');document.documentElement.style.setProperty('--sidebar-ml','250px');}</script>
        @stack('head')
        <style>
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: 'Inter', sans-serif;
                background:
                    radial-gradient(1100px 620px at 8% -8%, rgba(74, 158, 232, 0.12), transparent 58%),
                    radial-gradient(900px 560px at 108% 4%, rgba(45, 212, 168, 0.07), transparent 55%),
                    #122a51;
                background-attachment: fixed;
                color: #fff;
                min-height: 100vh;
                line-height: 1.5;
                letter-spacing: 0.1px;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
                text-rendering: optimizeLegibility;
            }
            ::-webkit-scrollbar { width: 5px; } ::-webkit-scrollbar-track { background: #0b1e3d; } ::-webkit-scrollbar-thumb { background: #1b3a6b; border-radius: 4px; transition: background 0.2s ease; } ::-webkit-scrollbar-thumb:hover { background: #2a4f8f; }

            /* Nexora modal system */
            .nexora-modal-overlay {
                position: fixed;
                inset: 0;
                background: rgba(6, 16, 34, 0.62);
                backdrop-filter: blur(3px);
                z-index: 20;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            /* Entry animations run only when the modal is opened (.open),
               never on page load. The overlay is always present in the DOM
               and hidden via each page's `opacity: 0` rule; putting the
               animation on the base element made it override that hidden
               state on every navigation, causing a brief modal flash. */
            .nexora-modal-overlay.open {
                animation: nexoraOverlayIn 0.2s ease-out;
            }

            .nexora-modal {
                background: linear-gradient(160deg, #16305a 0%, #132B52 55%, #0f2447 100%);
                border: 1px solid rgba(255, 255, 255, 0.08);
                border-radius: 16px;
                padding: 28px;
                width: 100%;
                max-width: 720px;
                margin: 16px;
                box-shadow: 0 24px 60px -12px rgba(0, 0, 0, 0.60);
                color: #fff;
                position: relative;
                overflow: hidden;
            }

            .nexora-modal-overlay.open .nexora-modal {
                animation: nexoraModalIn 0.24s cubic-bezier(0.22, 1, 0.36, 1);
            }

            @keyframes nexoraOverlayIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            @keyframes nexoraModalIn {
                from { opacity: 0; transform: translateY(12px) scale(0.98); }
                to { opacity: 1; transform: translateY(0) scale(1); }
            }

            .nexora-modal-logo {
                position: absolute;
                inset: 0;
                background-image: url('/images/Nexora_Logo_Transparent.png');
                background-repeat: no-repeat;
                background-position: center right 20px;
                background-size: 220px auto;
                opacity: 0.08;
                pointer-events: none;
                z-index: 0;
            }

            .nexora-modal > *:not(.nexora-modal-logo) {
                position: relative;
                z-index: 1;
            }

            .nexora-modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 24px;
            }

            .nexora-modal-title {
                font-size: 20px;
                font-weight: 700;
                color: #fff;
            }

            .nexora-modal-close {
                background: transparent;
                border: none;
                color: #fff;
                cursor: pointer;
                font-size: 24px;
                line-height: 1;
            }

            .nexora-modal-form {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 16px;
            }

            @media (max-width: 640px) {
                .nexora-modal-form {
                    grid-template-columns: 1fr;
                }
            }

            .nexora-modal-label {
                display: block;
                font-size: 12px;
                font-weight: 600;
                color: #fff;
                letter-spacing: 0.05em;
                text-transform: uppercase;
                margin-bottom: 6px;
            }

            .nexora-modal-input,
            .nexora-modal-select {
                width: 100%;
                padding: 10px 12px;
                border-radius: 12px;
                border: 1px solid #000;
                background: rgba(255, 255, 255, 0.9);
                color: #0f172a;
                font-family: 'Inter', sans-serif;
                outline: none;
                transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
            }

            .nexora-modal-input:focus,
            .nexora-modal-select:focus {
                border-color: #1B6FC8;
                background: #ffffff;
                box-shadow: 0 0 0 3px rgba(27, 111, 200, 0.18);
            }

            .nexora-modal-input:disabled {
                background: #f1f5f9;
                color: #64748b;
                cursor: not-allowed;
            }

            .nexora-modal-error {
                color: #ef4444;
                font-size: 11px;
                margin-top: 4px;
            }

            .nexora-modal-actions {
                display: flex;
                justify-content: flex-end;
                gap: 10px;
                margin-top: 24px;
            }

            .nexora-modal-btn-secondary {
                background: transparent;
                color: #fff;
                border: 1px solid rgba(255, 255, 255, 0.7);
                border-radius: 10px;
                padding: 10px 18px;
                font-weight: 600;
                cursor: pointer;
                transition: background-color 0.16s ease, border-color 0.16s ease;
            }

            .nexora-modal-btn-secondary:hover {
                background: rgba(255, 255, 255, 0.10);
                border-color: #fff;
            }

            .nexora-modal-btn-primary {
                background: #fff;
                color: #1B6FC8;
                border: 1px solid #000;
                border-radius: 10px;
                padding: 10px 18px;
                font-weight: 600;
                cursor: pointer;
                box-shadow: 0 4px 12px -4px rgba(0, 0, 0, 0.45);
                transition: transform 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
            }

            .nexora-modal-btn-primary:hover {
                transform: translateY(-1px);
                background: #f4f8ff;
                box-shadow: 0 8px 18px -6px rgba(0, 0, 0, 0.5);
            }

            .nexora-modal-btn-primary:active {
                transform: translateY(0);
            }

        </style>
        @stack('styles')
    </head>
    <body>
        @include('inventory::partials.header')
        <div id="main">
            <div class="sidebar-overlay" onclick="closeSidebarMobile()"></div>
            @include('inventory::partials.sidebar')
            <div id="page-content">
                @yield('content')
            </div>
        </div>
        @include('inventory::partials.sidebar-scripts')
        @stack('scripts')
        <script>
            // Auto-submit search/filter forms after the user stops typing
            document.querySelectorAll('form input[name="search"]').forEach(function (input) {
                let typingTimer;
                input.addEventListener('input', function () {
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(function () {
                        input.form.submit();
                    }, 700);
                });
            });
        </script>
    </body>
</html>
