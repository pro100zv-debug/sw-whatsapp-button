# SW WhatsApp Button

Botón flotante de WhatsApp para WordPress: adaptativo, ligero (sin jQuery en el frontend) y configurable desde **Ajustes → SW WhatsApp**.

## Instalación

1. Descarga `sw-whatsapp-button.zip` de la última [Release](../../releases/latest).
2. WordPress → Plugins → Añadir nuevo → Subir plugin → Activar.
3. Ajustes → SW WhatsApp → introduce el número con prefijo de país.

Las siguientes versiones llegan como actualización normal en **Plugins**.

## Publicar una nueva versión

Automático (recomendado):

1. Cambia el código y sube `Version:` en la cabecera de `sw-whatsapp-button.php` (por ejemplo `1.5.3`).
2. Escribe las novedades en `RELEASE_NOTES.md` (en español, breve).
3. Haz push a `main`. GitHub Actions crea la etiqueta `v1.5.3`, la Release con esas notas y el zip en ~1 minuto.
   Si la versión no cambia, no se publica nada.

Manual (opcional): GitHub → Releases → **Draft a new release** → etiqueta igual a `Version` → **Publish release**; Actions adjunta el zip.

Los sitios ven la actualización en las próximas horas (o al instante con «Comprobar si hay actualizaciones» en Plugins).

## Hooks

- `sw_whatsapp_phone` — cambiar el número por código.
- `sw_whatsapp_hide` — devolver `true` para ocultar el botón.

Las actualizaciones usan [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) (MIT).
