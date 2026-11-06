<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', __('Official Document')) - {{ Auth::user()->vendor->name }}</title>

    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f1f5f9;
            color: #0f172a;
            font-size: 13px;
            line-height: 1.45;
            -webkit-font-smoothing: antialiased;
        }

        /* Screen Presentation Toolbar */
        .print-toolbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0.75rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .print-btn {
            background: #4f46e5;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 0.6rem 1.25rem;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.15s ease;
        }
        .print-btn:hover {
            background: #4338ca;
        }

        .close-btn {
            background: #f8fafc;
            color: #475569;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0.6rem 1.1rem;
            font-weight: 600;
            font-size: 0.88rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            text-decoration: none;
        }
        .close-btn:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        /* A4 Page Container */
        .document-wrapper {
            max-width: 860px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .document-sheet {
            background: #ffffff;
            border-radius: 4px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08), 0 2px 6px rgba(0, 0, 0, 0.04);
            padding: 24mm 20mm;
            min-height: 297mm;
            position: relative;
        }

        /* Official Document Header */
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 1.25rem;
            margin-bottom: 1.5rem;
            gap: 1.5rem;
        }

        .doc-company {
            flex: 1;
        }
        .doc-company-name {
            font-family: 'Outfit', sans-serif;
            font-size: 1.4rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
            margin-bottom: 0.25rem;
        }
        .doc-company-details {
            font-size: 0.82rem;
            color: #475569;
            line-height: 1.4;
        }

        .doc-title-block {
            text-align: right;
        }
        .doc-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            margin-bottom: 0.25rem;
        }
        .doc-number {
            font-family: 'Courier New', monospace;
            font-size: 1.05rem;
            font-weight: 700;
            color: #4f46e5;
        }
        .doc-date {
            font-size: 0.82rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        /* Key-Value Metadata Grid */
        .doc-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }
        .doc-meta-item label {
            display: block;
            font-size: 0.72rem;
            text-transform: uppercase;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 0.15rem;
        }
        .doc-meta-item span {
            font-size: 0.9rem;
            font-weight: 600;
            color: #0f172a;
        }

        /* Tables */
        .doc-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }
        .doc-table th {
            background: #f1f5f9;
            color: #0f172a;
            font-weight: 700;
            text-align: left;
            padding: 0.6rem 0.65rem;
            border-top: 1px solid #cbd5e1;
            border-bottom: 2px solid #94a3b8;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .doc-table td {
            padding: 0.6rem 0.65rem;
            border-bottom: 1px solid #e2e8f0;
            color: #1e293b;
        }
        .doc-table tr:last-child td {
            border-bottom: 1px solid #94a3b8;
        }
        .doc-table .text-right {
            text-align: right;
        }
        .doc-table .text-center {
            text-align: center;
        }
        .doc-table .mono {
            font-family: 'Courier New', monospace;
        }

        /* Section Subheading inside Document */
        .doc-section-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            margin: 1.25rem 0 0.6rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Totals Block */
        .doc-totals {
            margin-left: auto;
            width: 330px;
            margin-bottom: 1.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
            padding: 0.75rem 1.1rem;
        }
        .doc-total-row {
            display: flex;
            justify-content: space-between;
            padding: 0.3rem 0;
            font-size: 0.85rem;
            color: #475569;
        }
        .doc-total-row.grand-total {
            border-top: 2px solid #0f172a;
            margin-top: 0.4rem;
            padding-top: 0.5rem;
            font-size: 1.05rem;
            font-weight: 800;
            color: #0f172a;
        }

        /* Signatures Block */
        .doc-signatures {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-top: 3.5rem;
            padding-top: 1rem;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .doc-sig-box {
            text-align: center;
        }
        .doc-sig-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 2.75rem;
        }
        .doc-sig-line {
            border-bottom: 1px dashed #64748b;
            margin-bottom: 0.35rem;
        }
        .doc-sig-sub {
            font-size: 0.72rem;
            color: #64748b;
        }

        /* Notes Box */
        .doc-notes {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 0.75rem 1rem;
            font-size: 0.82rem;
            color: #475569;
            margin-bottom: 1.5rem;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
                font-size: 10.5pt;
            }
            .print-toolbar, .no-print {
                display: none !important;
            }
            .document-wrapper {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .document-sheet {
                box-shadow: none !important;
                border-radius: 0 !important;
                padding: 0 !important;
                min-height: auto !important;
            }
            .doc-table th {
                background: #f1f5f9 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .doc-meta-grid, .doc-totals {
                background: #f8fafc !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            tr {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            @page {
                size: A4 portrait;
                margin: 12mm 15mm 15mm 15mm;
            }
        }
    </style>
</head>
<body>
    <!-- Top Floating Toolbar (Hidden when printing) -->
    <div class="print-toolbar no-print">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <button type="button" onclick="window.close(); if(!window.closed) history.back();" class="close-btn">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Close') }}
            </button>
            <div style="font-weight: 700; font-size: 0.95rem; color: #1e293b;">
                @yield('toolbar-title', __('Document Preview'))
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <button type="button" onclick="window.print();" class="print-btn">
                <i class="fa-solid fa-print"></i> {{ __('Print / Save PDF') }}
            </button>
        </div>
    </div>

    <!-- Main Sheet Container -->
    <div class="document-wrapper">
        <div class="document-sheet">
            @yield('content')
        </div>
    </div>

    @if(request()->query('auto_print'))
        <script>
            window.addEventListener('load', function() {
                setTimeout(function() {
                    window.print();
                }, 350);
            });
        </script>
    @endif
</body>
</html>

