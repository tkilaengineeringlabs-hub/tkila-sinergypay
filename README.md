# Tkila SinergyPay Gateway

Plugin de WordPress/WooCommerce para integrar SinergyPay mediante API.

## Objetivo actual

Implementar primero el flujo SPEI en Sandbox:

1. Cliente selecciona SinergyPay SPEI en WooCommerce.
2. WooCommerce crea el pedido.
3. El plugin crea la operación correspondiente en SinergyPay.
4. Solicita/recibe los datos SPEI (incluida la CLABE según la API).
5. Guarda únicamente los identificadores y datos no sensibles necesarios en el pedido.
6. Muestra al cliente las instrucciones de pago.
7. Recibe webhooks de SinergyPay y actualiza el pedido de WooCommerce de forma idempotente.

## Futuro

La arquitectura debe permitir incorporar tarjetas y tokenización cuando el comercio cuente con PCI DSS y SinergyPay habilite las credenciales/permisos correspondientes. Esos módulos NO deben capturar ni procesar tarjetas mientras permanezcan deshabilitados.

## Seguridad

- Nunca guardar API keys en el repositorio.
- Nunca registrar secretos en logs.
- Nunca almacenar CVV.
- No implementar captura directa de PAN hasta conocer y cumplir el flujo PCI autorizado por SinergyPay.
- Validar webhooks conforme a la documentación oficial antes de modificar estados de pedidos.

## Estado

Flujo SPEI Sandbox implementado hasta la generación de instrucciones y verificación criptográfica del webhook. Acreditación automática pendiente de confirmación documental de SinergyPay.

## Implementación SPEI Sandbox

Fuente del contrato: `docs/V3-Comercios-SPEI.pdf`, páginas 3-8. No se implementan tarjetas ni tokenización. Producción permanece bloqueada aunque exista el campo para guardar su credencial.

- `POST /orders/`: Basic con API key privada y contraseña vacía, User-Agent del plugin, JSON con `amount`, `description` y una `reference` única del pedido.
- `POST /orders/{order_id}/clabe`: misma autenticación, objeto JSON vacío, sin campos adicionales inventados.
- Se validan HTTP, `rc: 0`, estructura, importe y moneda de la orden remota. Se persisten identificador remoto, referencia propia, importe, ambiente, fechas, referencia numérica y CLABE mediante WooCommerce CRUD.
- El pedido queda en `on-hold` al generar instrucciones. Esto significa espera de pago, no acreditación. WooCommerce aplica sus acciones normales de inventario al cambiar a ese estado.
- Instrucciones en agradecimiento y Mi cuenta > pedido, con comprobación de propietario, permisos administrativos o clave del pedido para invitados. Se muestra CLABE, importe, referencia y vencimiento cuando la API los devuelve.
- La CLABE se sanitiza y almacena sin imponer longitud: el ejemplo del PDF tiene 16 dígitos. TODO SinergyPay: confirmar formato/corregir ejemplo.
- Compatible con checkout clásico. No se registra integración del método en Checkout Blocks.

### Configuración y prueba real

1. Instalar el plugin en WordPress con WooCommerce y PHP 8.0+; habilitar OpenSSL para webhooks.
2. En WooCommerce > Ajustes > Pagos > SinergyPay SPEI, seleccionar Sandbox, ingresar la **API key privada Sandbox** y habilitar el método. La tienda y el pedido deben utilizar MXN. Las credenciales se guardan exclusivamente en las opciones de WooCommerce; no añadirlas al repositorio.
3. Obtener las llaves públicas oficiales desde `https://media.sinergypay.mx/sw/public_keys.zip` (PDF p. 3). Configurar en “Llaves públicas del webhook (JSON)” un objeto cuyas claves sean los identificadores exactos usados por `security.key` y cuyos valores sean el contenido PEM público correspondiente. Los saltos de línea dentro de las cadenas JSON se representan con `\n`. No introducir llaves privadas. La configuración valida el PEM; la verificación exige una llave RSA. Sin llaves configuradas se devuelve 503.
4. Configurar en SinergyPay la URL HTTPS `https://TU-TIENDA/wp-json/tkila-sinergypay/v1/webhook`. El PDF no detalla el procedimiento de alta de la URL; solicitarlo a soporte si no está disponible en el portal.
5. Crear un pedido de prueba con el checkout clásico, comprobar orden remota y CLABE, y enviar una notificación Sandbox genuina. Hasta resolver la acreditación pendiente, verificar en el portal de SinergyPay y realizar cualquier acreditación manual mediante el proceso administrativo de la tienda.

El ajuste Debug controla los logs informativos. Los errores SPEI se registran siempre en WooCommerce con fuente `tkila-sinergypay` y contexto sanitizado (estado HTTP, motivo técnico, claves superiores de la respuesta y endpoint). Nunca se registran cuerpos de respuesta, credenciales, firmas ni datos bancarios.

### Webhook: autenticidad separada de acreditación

Se verifica la versión 1, firma Base64 y RSA PKCS#1 v1.5/SHA-512 de la cadena exacta `id|currency|amount|description|reference|date`; null se convierte en vacío. Los campos se verifican antes de sanitizar o reformatear. Se aceptan cadenas o null para conservar la representación firmada del ejemplo del PDF; valores JSON numéricos se rechazan de forma conservadora. La llave se busca exclusivamente en el mapa configurado: nunca se abre una ruta ni se descarga una URL indicada por el webhook.

La referencia firmada permite localizar inequívocamente el pedido (HPOS o almacenamiento tradicional). Se comparan referencia, descripción, moneda e importe contra el pedido y la intención persistida. `code`, `payments` y los demás campos no firmados no se utilizan para acreditar ni se almacenan como evidencia confiable.

Un webhook autenticado y correlacionado recibe **202**, con `payment_confirmed: false`. Se guardan exclusivamente los seis campos firmados y el indicador `_tkila_sinergypay_confirmation = pending_provider_specification`. No se llama a `payment_complete`, no se asigna ID de transacción y el webhook no modifica estado ni inventario. Repetir la misma notificación no repite escrituras. Las respuestas HTTP y este indicador son decisiones locales del plugin, no estados de SinergyPay.

La política pendiente está aislada en `Payment_Confirmation`. **TODO SinergyPay para cerrar la acreditación automática:** documentar que el `amount` firmado representa dinero efectivamente liquidado (incluyendo pagos parciales, excedentes y vencimiento), o proporcionar una consulta autenticada con contrato de respuesta suficiente para verificar pago, importe, moneda y asociación al pedido. El listado `/payments` del PDF carece de esquema de respuesta. Hasta entonces, ni una firma válida ni el contenido de `payments` disparan la acreditación.

### Reintentos y recuperación

Un mutex atómico por pedido serializa checkout y webhook. Se persiste la intención antes de cada POST. Un checkout repetido reutiliza los identificadores y la CLABE existentes; una respuesta perdida o inválida bloquea nuevos POST para evitar operaciones duplicadas. Si vence la orden, se requiere revisión, sin crear otra automáticamente.

TODO SinergyPay: documentar idempotencia y consulta/recuperación de órdenes y CLABE tras una respuesta incierta. Mientras tanto, un administrador debe reconciliar la operación en SinergyPay antes de reparar metadata mediante WooCommerce CRUD. No borrar indicadores de intento sin comprobar el resultado remoto. Si un proceso termina abruptamente, puede quedar la opción `tkila_sinergypay_lock_{order_id}`: eliminarla únicamente tras comprobar que no sigue ejecutándose y reconciliar cualquier POST pendiente. No hay expiración automática del mutex, para no permitir que dos procesos remotos se solapen.

### Verificación local

Ejecutar `php tests/run.php`: pruebas sin red ni credenciales para contrato HTTP, creación de orden/CLABE, reintentos, errores inciertos, firma real generada con RSA de prueba, alteraciones, correlación y ausencia de acreditación. No sustituyen pruebas en una instalación real de WooCommerce y Sandbox.

## Instalar el ZIP y realizar la primera orden

1. Preparar una tienda de pruebas con HTTPS público, PHP 8.0+ con OpenSSL y WooCommerce instalado y activo. No utilizar la tienda de producción para esta primera prueba.
2. En WordPress > Plugins > Añadir nuevo > Subir plugin, seleccionar `tkila-sinergypay-0.1.0-sandbox.zip`, instalarlo y activar **Tkila SinergyPay Gateway**. El ZIP contiene una única carpeta raíz `tkila-sinergypay/` y todas sus dependencias PHP. No contiene credenciales ni el PDF confidencial.
3. En WooCommerce > Ajustes > General, establecer la moneda en **Peso mexicano (MXN)**.
4. En la página asignada como Finalizar compra, utilizar el checkout clásico con el shortcode `[woocommerce_checkout]`, no el bloque Checkout. Si se utiliza un carrito por bloques que advierte incompatibilidad, cambiar también el carrito al shortcode `[woocommerce_cart]`. Revisar la asignación de páginas en WooCommerce > Ajustes > Avanzado.
5. Ir a WooCommerce > Ajustes > Pagos y localizar **SinergyPay SPEI**. Abrir su configuración: activar el método, Ambiente **Sandbox**, Sandbox API Key **privada**, título y descripción deseados, Debug activado para esta prueba. Dejar Production API Key vacía. Guardar cambios. La credencial se introduce solo en esa pantalla.
6. Configurar las llaves públicas y la URL del webhook como se describe arriba. Guardar el JSON válido de llaves públicas. El endpoint solo acepta POST; abrirlo en el navegador con GET no valida su funcionamiento. El alta de la URL y la simulación de pagos deben seguir el procedimiento que proporcione SinergyPay; el PDF no describe esos pasos del portal.
7. Crear un producto simple, virtual, con precio de prueba positivo en MXN (por ejemplo 10.00), publicado y con inventario suficiente. Marcarlo virtual evita depender de una configuración de envío en esta primera orden.
8. Desde una ventana de cliente, añadirlo al carrito, completar los datos obligatorios del checkout, elegir SinergyPay SPEI y realizar el pedido una sola vez.
9. Comprobar que se llega a la página de agradecimiento, se muestran CLABE e importe y, cuando la API los entrega, referencia y vencimiento. En administración el pedido debe estar **En espera**. Un cliente registrado también puede consultar las instrucciones en Mi cuenta > Pedidos > Ver.
10. Contrastar en SinergyPay Sandbox el importe y la referencia interna con la orden creada. Los datos internos se guardan con prefijo `_tkila_sinergypay_` y no necesariamente aparecen en el panel estándar de campos personalizados de WordPress.
11. Solicitar/generar una notificación genuina mediante el procedimiento Sandbox de SinergyPay. Una notificación válida y correlacionada debe recibir HTTP **202** y `payment_confirmed: false`. El pedido debe continuar En espera. La recepción verificada se guarda como metadata; no se añade una nota de pago ni se acredita automáticamente.
12. Si ocurre un error, revisar WooCommerce > Estado > Registros, fuente `tkila-sinergypay`, y la respuesta de entrega del webhook en SinergyPay. No repetir ciegamente un POST ni borrar metadata de intento: primero reconciliar con el proveedor. Falta de llaves devuelve 503, firma inválida 401 y discrepancia de correlación/importe 409.

Para reproducir el paquete: `python3 scripts/build-plugin.py`. Verificar localmente con `php tests/run.php`, `php tests/bootstrap.php` y `php tests/bootstrap.php tkila-sinergypay.php --without-woocommerce`. Las pruebas de bootstrap comprueban la carga y registro con dobles de WordPress/WooCommerce, no certifican la pantalla de una tienda real.
