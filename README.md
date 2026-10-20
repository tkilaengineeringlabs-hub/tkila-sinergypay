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

Estructura inicial preparada para comenzar la integración SPEI en Sandbox.
