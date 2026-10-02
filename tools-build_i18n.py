# -*- coding: utf-8 -*-
"""Build POT + de_DE po/mo for GEO Insights.

Self-contained: extracts msgids from the plugin PHP files, loads the
German dictionary from tools-i18n-de.json (same directory), fails loudly
on missing translations, then writes languages/*.pot|po|mo.

Usage:  python tools-build_i18n.py   (requires: pip install polib)
"""
import datetime
import json
import os
import re
import sys

import polib

HERE = os.path.dirname(os.path.abspath(__file__))
PLUGIN = os.path.join(HERE, 'geo-insights-ai')
DICT = os.path.join(HERE, 'tools-i18n-de.json')

# Version from the plugin header, so the catalogs always track the release.
_main = open(os.path.join(PLUGIN, 'geo-insights-ai.php'), encoding='utf-8').read()
VERSION = re.search(r'^\s*\*\s*Version:\s*([0-9][0-9a-zA-Z.\-]*)', _main, re.M).group(1)

FUNCS = r'(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)'
PATTERN = re.compile(
    FUNCS + r"\(\s*"
    r"(?:'((?:[^'\\]|\\.)*)'|\"((?:[^\"\\]|\\.)*)\")"
    r"\s*,\s*'geo-insights-ai'\s*\)"
)


def extract_msgids():
    msgids = []
    seen = set()
    for root, _dirs, files in os.walk(PLUGIN):
        for name in sorted(files):
            if not name.endswith('.php'):
                continue
            code = open(os.path.join(root, name), encoding='utf-8').read()
            for m in PATTERN.finditer(code):
                raw = m.group(1) if m.group(1) is not None else m.group(2)
                if m.group(1) is not None:
                    s = raw.replace("\\'", "'").replace('\\\\', '\\')
                else:
                    s = raw.replace('\\"', '"').replace('\\\\', '\\')
                if s not in seen:
                    seen.add(s)
                    msgids.append(s)
    return msgids


def main():
    msgids = extract_msgids()
    de = json.load(open(DICT, encoding='utf-8'))

    missing = [m for m in msgids if m not in de]
    if missing:
        print('MISSING TRANSLATIONS (%d) - add them to tools-i18n-de.json:' % len(missing))
        for m in missing:
            print(repr(m))
        sys.exit(1)

    obsolete = [k for k in de if k not in msgids]
    if obsolete:
        print('note: %d dictionary entries are unused (kept in JSON, omitted from po):' % len(obsolete))
        for k in obsolete:
            print('  ' + repr(k[:70]))

    now = datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%d %H:%M%z')
    meta_common = {
        'Project-Id-Version': 'GEO Insights %s' % VERSION,
        'Report-Msgid-Bugs-To': 'https://wordpress.org/support/plugin/geo-insights-ai',
        'MIME-Version': '1.0',
        'Content-Type': 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding': '8bit',
        'POT-Creation-Date': now,
        'X-Generator': 'GEO Insights build script',
    }

    pot = polib.POFile()
    pot.metadata = dict(meta_common)
    for m in msgids:
        pot.append(polib.POEntry(msgid=m, msgstr=''))
    pot.save(os.path.join(PLUGIN, 'languages', 'geo-insights-ai.pot'))

    po = polib.POFile()
    po.metadata = dict(meta_common)
    po.metadata.update({
        'PO-Revision-Date': now,
        'Language': 'de_DE',
        'Plural-Forms': 'nplurals=2; plural=(n != 1);',
        'Last-Translator': 'Lucas <lcswll02@gmail.com>',
        'Language-Team': 'German',
    })
    for m in msgids:
        po.append(polib.POEntry(msgid=m, msgstr=de[m]))
    po.save(os.path.join(PLUGIN, 'languages', 'geo-insights-ai-de_DE.po'))
    po.save_as_mofile(os.path.join(PLUGIN, 'languages', 'geo-insights-ai-de_DE.mo'))
    print('OK: %d strings -> POT + de_DE.po + de_DE.mo' % len(msgids))


if __name__ == '__main__':
    main()
