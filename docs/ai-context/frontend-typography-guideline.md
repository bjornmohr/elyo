# ELYO — Typografie- & Lesbarkeits-Guideline (Web / Angular + Tailwind)

Verbindlich für **alle** Views (Employee, Company, Admin). Ableitung aus der UX-Analyse
des Employee-Dashboards (`demo/new-employee-features`): das Grundlayout ist gut, aber
tragende Informationen stehen durchgehend in 10–12 px und sind auf Desktop schwer lesbar.

## Grundprinzip

> **Kein tragender Text unter 12 px. Fließtext und Werte ≥ 14 px.**
> Widgets dürfen atmen: lieber weniger Spalten und größere Kacheln als vier gedrängte.

## Typo-Skala (Tailwind)

| Rolle | Klasse | px | Einsatz |
|---|---|---|---|
| Seiten-H1 | `text-2xl` (bisher `text-xl`) | 24 | Seitentitel „Hallo!", „Deine Maßnahmen" |
| Karten-H2/H3 | `text-lg` | 18 | Karten-Überschriften |
| Sektions-Label (uppercase) | `text-xs` (bisher `text-[10px]`/`text-[11px]`) | 12 | „Körpersignale", „Übungen", Badges |
| Kachel-Zahl / KPI | `text-3xl` (bisher `text-2xl`) | 30 | Metrik-Werte |
| Fließtext / Werte | `text-sm` | 14 | Sublines, Meta, Listenwerte |
| Sekundär / Meta | **min. `text-sm` (14)**, nur wo unkritisch `text-xs` (12) | 14/12 | Datum, „letzte Woche …" |
| **Verboten** | `text-[10px]`, `text-[11px]` | 10/11 | ersatzlos entfernen |

## Kontrast

- Sekundärtext **nicht heller als `text-slate-500`** (`#64748b`). `text-slate-400`/`-300`
  nur für rein dekorative Elemente (Trennpunkte), nie für lesbaren Inhalt.
- Text auf farbigem Grund (Teal-Hero, Amber-Banner): Deckkraft ≥ 90 %, Größe ≥ 13 px.
  `text-teal-100/80` und `text-[11px]` auf Teal ersetzen durch volltonigen Text ≥ 13 px.

## Widget-Dichte

- Metrik-Kacheln: auf breiten Screens **max. 3–4 Spalten**, Kachel-Padding ≥ `p-5`,
  Progressbar-Höhe ≥ `h-2`.
- Content-Container: Volle Breite (`w-full`) nur mit `max-w-5xl` begrenzen, damit
  Zeilen nicht zu lang und Kacheln nicht zu breit/leer werden.
- Trend/Status als farbiges **Chip mit Wort** („↓ besser"), nicht als winziger Pfeil allein.

## Touch / Klickflächen

- Interaktive Elemente (Buttons, Icon-Links) ≥ 44 × 44 px effektive Fläche.
  `p-2`-Icon-Buttons (z. B. der `←`-Zurück-Link) auf `p-2.5`/`min-h-11` anheben.

## Font-Familien (bereits gesetzt, unverändert lassen)

- Body: `--font-body: 'DM Sans'`
- Headings (`h1`–`h6`): `--font-display: 'Fraunces'`
