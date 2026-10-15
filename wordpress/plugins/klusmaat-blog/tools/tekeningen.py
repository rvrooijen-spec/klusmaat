"""Maakt de demotekeningen voor het voorbeeldartikel (PNG, 1200x800) in de huisstijl. Gebruik: python3 tekeningen.py <uitmap> <fontmap>"""
import sys, os
from playwright.sync_api import sync_playwright
uit, fonts = sys.argv[1], sys.argv[2]
INK, PEN, PAP, GEEL, ROZE, BETON, POT = '#161B26', '#2547B5', '#FCFCF9', '#F4E8A0', '#F2CBCB', '#E3E6DF', '#4B5260'
def kader(inhoud, label):
    return f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" width="1200" height="800">
<rect width="1200" height="800" fill="{PAP}"/>
<g fill="none" stroke="{INK}" stroke-width="5" stroke-linejoin="miter" stroke-linecap="butt">{inhoud}</g>
<rect x="0" y="716" width="1200" height="84" fill="{INK}"/>
<text x="40" y="772" font-family="Kalam" font-weight="700" font-size="44" fill="{PAP}">{label}</text></svg>'''
def muur(x=120,y=70,b=960,h=560): return f'<rect x="{x}" y="{y}" width="{b}" height="{h}" fill="{BETON}"/>'
S = {}
S['1'] = kader(muur(h=430) + f'''<rect x="120" y="500" width="960" height="130" fill="{ROZE}" opacity=".55"/>
<path d="M120 500H1080" /><rect x="300" y="360" width="360" height="190" fill="{PAP}"/><rect x="300" y="300" width="360" height="70" fill="{PAP}"/>
<path d="M280 340 L700 340 L720 570 L260 570Z" fill="#D5E4EE" opacity=".85"/><path d="M300 380 L680 380 M290 430 L690 430 M280 480 L700 480" stroke="{PEN}" stroke-width="3"/>
<rect x="760" y="440" width="200" height="110" fill="#D5E4EE" opacity=".85"/><path d="M760 440h200v110H760z"/>
<path d="M120 630 H1080" stroke-width="3"/>''', 'Folie over vloer en meubels')
S['2'] = kader(muur() + f'''<circle cx="470" cy="330" r="58" fill="{PAP}"/><path d="M430 350 q40 -50 80 -10" stroke="{POT}" stroke-width="3"/>
<g transform="rotate(-28 760 420)"><rect x="640" y="400" width="260" height="46" fill="#D5DADF"/><rect x="900" y="408" width="170" height="30" fill="{GEEL}"/></g>
<path d="M430 330 q40 -30 80 0" stroke="{PEN}" stroke-width="7"/><path d="M820 200 q-70 80 -300 110" stroke="{PEN}" stroke-width="4"/><path d="M520 310 l36 -6 m-36 6 l10 30" stroke="{PEN}" stroke-width="4"/>''', 'Plamuur in het gat, strak afstrijken')
S['3'] = kader(muur(h=430) + f'''<rect x="120" y="500" width="960" height="80" fill="{PAP}"/><path d="M120 500H1080"/><rect x="120" y="580" width="960" height="50" fill="{ROZE}" opacity=".5"/>
<rect x="120" y="478" width="960" height="22" fill="{GEEL}"/><rect x="780" y="270" width="110" height="150" fill="{PAP}"/><circle cx="835" cy="345" r="24"/><rect x="766" y="256" width="138" height="178" fill="none" stroke="{GEEL}" stroke-width="22"/>''', 'Tape langs plint en stopcontact')
S['4'] = kader(f'''<path d="M120 70 H600 V630 H120Z" fill="{BETON}"/><path d="M600 70 L1080 120 V600 L600 630Z" fill="#D8DBD3"/>
<rect x="600" y="70" width="44" height="560" fill="{PEN}" stroke="none" opacity=".9"/><path d="M600 70V630" stroke-width="6"/>
<g transform="rotate(14 760 440)"><rect x="700" y="400" width="120" height="70" fill="{GEEL}"/><rect x="820" y="418" width="230" height="34" fill="{INK}"/></g><path d="M610 120 V560" stroke="{PEN}" stroke-width="4" stroke-dasharray="14 12"/>''', 'Eerst de randen, ongeveer 5 cm breed')
S['5'] = kader(muur() + f'''<path d="M220 150 L360 560 L500 150 L640 560 L780 150 L920 560" stroke="{PEN}" stroke-width="22" stroke-linejoin="round" opacity=".9"/>
<rect x="700" y="180" width="330" height="80" fill="{GEEL}" transform="rotate(-18 860 230)"/><path d="M1010 170 L1060 150 L1060 350" transform="rotate(-18 860 230)"/>''', 'Rol in een W en strijk daarna uit')
S['6'] = kader(muur() + f'''<rect x="120" y="70" width="520" height="560" fill="{PEN}" stroke="none" opacity=".85"/><path d="M640 70V630" stroke-width="6"/>
<path d="M640 330 L860 250 L1000 220" stroke="{GEEL}" stroke-width="34"/><path d="M640 330 L860 250 L1000 220" stroke-width="5" transform="translate(0 -17)"/><path d="M640 330 L860 250 L1000 220" stroke-width="5" transform="translate(0 17)"/>
<path d="M700 440 C760 400 820 360 880 300" stroke="{INK}" stroke-width="4" stroke-dasharray="8 10"/>''', 'Tape er schuin af, terwijl de verf nog nat is')
html = '<style>@font-face{font-family:Kalam;font-weight:700;src:url("file://%s/kalam-700.woff")}body{margin:0}</style>' % fonts
os.makedirs(uit, exist_ok=True)
with sync_playwright() as p:
    b = p.chromium.launch(); pg = b.new_page(viewport={'width':1200,'height':800})
    for n, svg in S.items():
        tmp = os.path.join(uit, 'tmp.html'); open(tmp, 'w').write(html + svg); pg.goto('file://' + os.path.abspath(tmp)); pg.evaluate('document.fonts.ready'); pg.wait_for_timeout(400)
        pg.screenshot(path=os.path.join(uit, f'stap-{n}.png'))
    b.close()
os.remove(os.path.join(uit, 'tmp.html'))
