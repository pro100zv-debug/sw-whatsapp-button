# SW WhatsApp Button

Botón flotante de WhatsApp para WordPress: adaptativo, ligero (sin jQuery en el frontend) y configurable desde **Ajustes → WhatsApp**.

## Instalación

1. Descarga `sw-whatsapp-button.zip` de la última [Release](../../releases/latest).
2. WordPress → Plugins → Añadir nuevo → Subir plugin → Activar.
3. Ajustes → WhatsApp → introduce el número con prefijo de país.

Las siguientes versiones llegan como actualización normal en **Plugins**.

## Publicar una nueva versión

1. Cambia el código y sube `Version:` en la cabecera de `sw-whatsapp-button.php` (por ejemplo `1.3.1`).
2. Haz commit en `main`.
3. GitHub → Releases → **Draft a new release** → etiqueta `v1.3.1` (debe coincidir con `Version`) → describe los cambios → **Publish release**.
4. GitHub Actions genera y adjunta `sw-whatsapp-button.zip` en ~1 minuto. Los sitios ven la actualización en las próximas horas (o al instante con «Comprobar actualizaciones» en Plugins).

## Hooks

- `sw_whatsapp_phone` — cambiar el número por código.
- `sw_whatsapp_hide` — devolver `true` para ocultar el botón.

Las actualizaciones usan [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) (MIT).
