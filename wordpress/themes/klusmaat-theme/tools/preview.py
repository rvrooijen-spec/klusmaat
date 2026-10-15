#!/usr/bin/env python3
"""Bouwt een statisch voorbeeld van de homepage uit de bestanden van het thema, zonder WordPress.

Gebruik: python3 tools/preview.py <uitvoermap> [--inline-fonts]
Het voorbeeld is bedoeld om de stijl te beoordelen. WordPress zelf blijft de bron: de global-stijlen
hieronder bootsen alleen na wat theme.json daar genereert.
"""
import base64, pathlib, re, shutil, sys

root = pathlib.Path(__file__).resolve().parent.parent
out = pathlib.Path(sys.argv[1])
inline = "--inline-fonts" in sys.argv


def lees(p):
    return (root / p).read_text(encoding="utf-8")


def pattern(slug):
    tekst = lees(f"patterns/{slug.split('/')[1]}.php")
    return re.sub(r"^<\?php.*?\?>\s*", "", tekst, flags=re.S)


def onderdeel(naam):
    return lees(f"parts/{naam}.html")


html = lees("templates/front-page.html")
html = re.sub(r'<!-- wp:template-part \{"slug":"(\w+)"[^}]*\} /-->', lambda m: onderdeel(m.group(1)), html)
html = re.sub(r'<!-- wp:pattern \{"slug":"([\w/]+)"\} /-->', lambda m: pattern(m.group(1)), html)

# Dynamische blokken die WordPress zelf rendert.
html = html.replace('<!-- wp:site-title {"level":0} /-->', '<p class="wp-block-site-title"><a href="/">Klusmaat</a></p>')
nav = re.search(r"<!-- wp:navigation .*?<!-- /wp:navigation -->", html, flags=re.S)
if nav:
    links = re.findall(r'"label":"([^"]+)","url":"([^"]+)".*?(?:"className":"([^"]+)")?\s*/-->', nav.group(0))
    items = "".join(
        f'<a href="{u}"{" class=\"km-knop-rand\"" if c else ""}>{l}</a>' for l, u, c in links
    )
    html = html.replace(nav.group(0), f'<nav class="km-menu" aria-label="Hoofdmenu">{items}</nav>')

html = re.sub(r"<!--.*?-->", "", html, flags=re.S)
html = re.sub(r"\n{3,}", "\n\n", html)

fonts = {"archivo-var.woff": ("Archivo", "400 900", "62% 125%"), "kalam-400.woff": ("Kalam", "400", None), "kalam-700.woff": ("Kalam", "700", None)}
face = ""
for bestand, (fam, gew, breedte) in fonts.items():
    pad = root / "assets/fonts" / bestand
    if inline:
        src = "data:font/woff;base64," + base64.b64encode(pad.read_bytes()).decode()
    else:
        src = f"assets/fonts/{bestand}"
    face += f"@font-face{{font-family:'{fam}';src:url({src}) format('woff');font-weight:{gew};{f'font-stretch:{breedte};' if breedte else ''}font-display:swap}}\n"

basis = """
body{background:var(--km-beton);color:var(--km-inkt);font-family:var(--km-font-gedrukt);font-size:18px;line-height:1.5}
a{color:var(--km-balpen);text-decoration:underline}
h1,h2,h3{font-family:var(--km-font-gedrukt);font-weight:850;line-height:1.05;letter-spacing:-1px}
h1{font-size:clamp(42px,6.2vw,84px);line-height:.98;letter-spacing:-2px}
h2{font-size:clamp(30px,4vw,48px)}
h3{font-size:clamp(22px,2vw,24px);font-weight:700;line-height:1.25;letter-spacing:0}
"""
css = lees("assets/css/klusmaat.css")
doc = f"""<!doctype html>
<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Klusmaat</title>
<style>{face}{basis}{css}</style></head>
<body>{html}</body></html>
"""
out.mkdir(parents=True, exist_ok=True)
(out / "index.html").write_text(doc, encoding="utf-8")
if not inline:
    shutil.copytree(root / "assets/fonts", out / "assets/fonts", dirs_exist_ok=True)
print("klaar:", out / "index.html", len(doc), "bytes")
