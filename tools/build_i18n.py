"""Build .pot/.po/.mo for the SW WhatsApp plugin.

Usage: python3 -I build_i18n.py <plugin_dir>
Extracts every translatable string from sw-whatsapp-button.php, checks that
each one has a Spanish and a Russian translation, and writes languages/.
"""
import re
import struct
import sys
from pathlib import Path

DOMAIN = "sw-whatsapp"

TR = {
    "Floating, responsive WhatsApp button. Number, message, colors and position in Settings → SW WhatsApp.": (
        "Botón flotante de WhatsApp adaptativo. Número, mensaje, colores y posición en Ajustes → SW WhatsApp.",
        "Адаптивная плавающая кнопка WhatsApp. Номер, сообщение, цвета и положение — в Настройки → SW WhatsApp.",
    ),
    "Settings": ("Ajustes", "Настройки"),
    "Button icon": ("Icono del botón", "Иконка кнопки"),
    "Enable": ("Activar", "Включить"),
    "Show the button on the website": ("Mostrar el botón en la web", "Показывать кнопку на сайте"),
    "WhatsApp number": ("Número de WhatsApp", "Номер WhatsApp"),
    "With country code, without “+” or spaces (they are removed automatically). Without a number the button is not shown.": (
        "Con prefijo de país, sin «+» ni espacios (se limpian solos). Sin número el botón no se muestra.",
        "С кодом страны, без «+» и пробелов (они удаляются автоматически). Без номера кнопка не показывается.",
    ),
    "Predefined message": ("Mensaje predefinido", "Готовое сообщение"),
    "Hi, I have a question about: {title}": (
        "Hola, tengo una consulta sobre: {title}",
        "Здравствуйте, у меня вопрос по: {title}",
    ),
    "Text already typed when the chat opens. Empty = empty chat. Variables: %1$s — page title, %2$s — page link.": (
        "Texto que aparece ya escrito al abrir el chat. Vacío = chat vacío. Variables: %1$s — título de la página, %2$s — enlace de la página.",
        "Текст, который уже будет написан при открытии чата. Пусто — чат пустой. Переменные: %1$s — заголовок страницы, %2$s — ссылка на страницу.",
    ),
    "Text next to the button": ("Texto junto al botón", "Текст рядом с кнопкой"),
    "Let’s talk?": ("¿Hablamos?", "Поговорим?"),
    "Desktop and tablet only (≥ 768px). Empty = no text.": (
        "Solo en escritorio y tablet (≥ 768px). Vacío = sin texto.",
        "Только на компьютере и планшете (≥ 768px). Пусто — без текста.",
    ),
    "Colors": ("Colores", "Цвета"),
    "Button background": ("Fondo del botón", "Фон кнопки"),
    "Icon": ("Icono", "Иконка"),
    "Background on hover": ("Fondo al pasar el ratón", "Фон при наведении"),
    "Optional. Empty (“Clear”) = the button does not change color on hover.": (
        "Opcional. Vacío («Borrar») = el botón no cambia de color al pasar el ratón.",
        "Необязательно. Пусто («Сброс») — кнопка не меняет цвет при наведении.",
    ),
    "Pulse": ("Pulso", "Пульсация"),
    "Pulse on hover": ("Pulso al pasar el ratón", "Пульсация при наведении"),
    "Optional. Empty “Pulse” = same color as the button background. Empty “Pulse on hover” = no change. Only when the pulse animation is enabled (below, in Options).": (
        "Opcionales. «Pulso» vacío = mismo color que el fondo del botón. «Pulso al pasar el ratón» vacío = no cambia. Solo si la animación de pulso está activada (más abajo, en Opciones).",
        "Необязательно. Пустая «Пульсация» — цвет фона кнопки. Пустая «Пульсация при наведении» — не меняется. Работает, только если включена анимация пульсации (ниже, в «Опциях»).",
    ),
    "WhatsApp (official, included)": ("WhatsApp (oficial, incluido)", "WhatsApp (официальная, встроенная)"),
    "Upload your own": ("Subir el tuyo", "Загрузить свою"),
    "The official icon uses the “Icon” color.": (
        "El icono oficial toma el color «Icono».",
        "Официальная иконка окрашивается в цвет «Иконка».",
    ),
    "Choose": ("Elegir", "Выбрать"),
    "Remove": ("Quitar", "Убрать"),
    "SVG/PNG from the media library, square with a transparent background. Shown as is (not recolored).": (
        "SVG/PNG de la biblioteca de medios, cuadrado y con fondo transparente. Se muestra tal cual (no se recolorea).",
        "SVG/PNG из медиатеки, квадратная, с прозрачным фоном. Показывается как есть (не перекрашивается).",
    ),
    "Position": ("Posición", "Положение"),
    "Bottom right": ("Abajo a la derecha", "Внизу справа"),
    "Bottom left": ("Abajo a la izquierda", "Внизу слева"),
    "Side margin": ("Margen lateral", "Отступ сбоку"),
    "Bottom margin": ("Margen inferior", "Отступ снизу"),
    "Size": ("Tamaño", "Размер"),
    "On mobile the button is automatically reduced to 85%.": (
        "En móvil el botón se reduce automáticamente al 85%.",
        "На телефонах кнопка автоматически уменьшается до 85%.",
    ),
    "Devices": ("Dispositivos", "Устройства"),
    "Desktop (> 1024px)": ("Escritorio (> 1024px)", "Компьютер (> 1024px)"),
    "Mobile and tablet (≤ 1024px)": ("Móvil y tablet (≤ 1024px)", "Телефон и планшет (≤ 1024px)"),
    "Options": ("Opciones", "Опции"),
    "Pulse animation": ("Animación de pulso", "Анимация пульсации"),
    "Open in a new tab": ("Abrir en pestaña nueva", "Открывать в новой вкладке"),
    "Hide on WooCommerce cart and checkout": (
        "Ocultar en carrito y checkout de WooCommerce",
        "Скрывать в корзине и оформлении заказа WooCommerce",
    ),
    "Hide on pages (ID)": ("Ocultar en páginas (ID)", "Скрывать на страницах (ID)"),
    "Page, post or product IDs separated by commas.": (
        "IDs de páginas, entradas o productos separados por comas.",
        "ID страниц, записей или товаров через запятую.",
    ),
    "Analytics: each click sends %1$s — create a custom event trigger %2$s in GTM.": (
        "Analítica: cada clic envía %1$s — crea en GTM un activador de evento personalizado %2$s.",
        "Аналитика: каждый клик отправляет %1$s — создайте в GTM триггер пользовательского события %2$s.",
    ),
    "Write to us on WhatsApp": ("Escríbenos por WhatsApp", "Напишите нам в WhatsApp"),
}

LOCALES = {
    "es_ES": (0, "Spanish (Spain)", "nplurals=2; plural=(n != 1);"),
    "ru_RU": (1, "Russian", "nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);"),
}


def extract(php: str):
    found = []
    header = re.search(r"^\s*\*\s*Description:\s*(.+?)\s*$", php, re.M)
    if header:
        found.append(header.group(1))
    pat = re.compile(r"(?:esc_html__|esc_html_e|esc_attr__|esc_attr_e|__|_e)\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'([^']+)'\s*\)")
    for m in pat.finditer(php):
        if m.group(2) != DOMAIN:
            continue
        s = m.group(1).replace("\\'", "'").replace("\\\\", "\\")
        if s not in found:
            found.append(s)
    return found


def po_escape(s: str) -> str:
    return s.replace("\\", "\\\\").replace('"', '\\"').replace("\n", "\\n")


def write_po(path: Path, header: str, pairs):
    lines = ['msgid ""', 'msgstr ""']
    lines += [f'"{po_escape(h)}\\n"' for h in header.strip().split("\n")]
    lines.append("")
    for msgid, msgstr in pairs:
        if "%" in msgid:
            lines.append("#, php-format")
        lines.append(f'msgid "{po_escape(msgid)}"')
        lines.append(f'msgstr "{po_escape(msgstr)}"')
        lines.append("")
    path.write_text("\n".join(lines), encoding="utf-8")


def write_mo(path: Path, header: str, pairs):
    entries = [("", header.strip() + "\n")] + sorted(pairs)
    ids = [k.encode("utf-8") for k, _ in entries]
    strs = [v.encode("utf-8") for _, v in entries]
    n = len(entries)
    o_ids = 7 * 4
    o_strs = o_ids + n * 8
    data_start = o_strs + n * 8
    id_table, str_table, blob = [], [], b""
    for b in ids:
        id_table.append((len(b), data_start + len(blob)))
        blob += b + b"\0"
    for b in strs:
        str_table.append((len(b), data_start + len(blob)))
        blob += b + b"\0"
    out = struct.pack("<7I", 0x950412DE, 0, n, o_ids, o_strs, 0, 0)
    for l, o in id_table:
        out += struct.pack("<2I", l, o)
    for l, o in str_table:
        out += struct.pack("<2I", l, o)
    path.write_bytes(out + blob)


def main():
    plugin = Path(sys.argv[1])
    php = (plugin / "sw-whatsapp-button.php").read_text(encoding="utf-8")
    strings = extract(php)
    missing = [s for s in strings if s not in TR]
    unused = [s for s in TR if s not in strings]
    if missing:
        sys.exit("Missing translations:\n" + "\n".join(missing))
    if unused:
        print("Note: unused entries:", unused)
    for s, (es, ru) in TR.items():
        for t in (es, ru):
            if s.count("%1$s") != t.count("%1$s") or s.count("%2$s") != t.count("%2$s") or s.count("{title}") != t.count("{title}"):
                sys.exit(f"Placeholder mismatch: {s!r} -> {t!r}")

    langdir = plugin / "languages"
    langdir.mkdir(exist_ok=True)
    pot_header = (
        "Project-Id-Version: SW WhatsApp Button\n"
        "MIME-Version: 1.0\n"
        "Content-Type: text/plain; charset=UTF-8\n"
        "Content-Transfer-Encoding: 8bit\n"
        f"X-Domain: {DOMAIN}"
    )
    write_po(langdir / f"{DOMAIN}.pot", pot_header, [(s, "") for s in strings])
    for loc, (idx, name, plural) in LOCALES.items():
        header = (
            "Project-Id-Version: SW WhatsApp Button\n"
            f"Language: {loc}\n"
            "MIME-Version: 1.0\n"
            "Content-Type: text/plain; charset=UTF-8\n"
            "Content-Transfer-Encoding: 8bit\n"
            f"Plural-Forms: {plural}\n"
            f"X-Domain: {DOMAIN}"
        )
        pairs = [(s, TR[s][idx]) for s in strings]
        write_po(langdir / f"{DOMAIN}-{loc}.po", header, pairs)
        write_mo(langdir / f"{DOMAIN}-{loc}.mo", header, pairs)
    print(f"{len(strings)} strings, locales: {', '.join(LOCALES)}")


if __name__ == "__main__":
    main()
