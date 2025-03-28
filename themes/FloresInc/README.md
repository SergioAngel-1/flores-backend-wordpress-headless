# Flores INC - WordPress Theme & Functions

Este directorio contiene el tema personalizado de WordPress y las funciones específicas desarrolladas para la tienda en línea de Flores INC. El tema está diseñado para funcionar como backend headless que proporciona una API REST para el frontend React.

## Estructura del Tema

El tema está organizado en varios directorios y archivos funcionales:

```
FloresInc/
├── inc/                  # Funciones y endpoints personalizados
│   ├── banner-functions.php             # Funciones para banners y sliders
│   ├── cors-functions.php               # Configuración de CORS para la API
│   ├── featured-categories-functions.php # Categorías destacadas
│   ├── hiperofertas-functions.php       # Funcionalidad de ofertas especiales
│   ├── legal-functions.php              # Páginas legales (términos, privacidad)
│   ├── menu-functions.php               # Configuración de menús
│   ├── profile-functions.php            # Funciones de perfil de usuario
│   ├── promotional-grid-functions.php   # Grilla promocional
│   ├── user-addresses-functions.php     # Gestión de direcciones de usuario
│   ├── user-profile-functions.php       # Actualización de perfil de usuario
│   └── woocommerce-functions.php        # Personalización de WooCommerce
├── templates/            # Plantillas de página
├── assets/               # Recursos estáticos (CSS, JS, imágenes)
├── functions.php         # Archivo principal de funciones
├── style.css             # Hoja de estilos principal
└── index.php             # Archivo principal del tema
```

## Funcionalidades Principales

### API REST Personalizada

El tema extiende la API REST de WordPress y WooCommerce con endpoints personalizados para:

1. **Gestión de Usuarios**
   - Registro y autenticación
   - Actualización de perfil
   - Gestión de direcciones

2. **Catálogo de Productos**
   - Productos destacados
   - Categorías personalizadas
   - Filtros avanzados

3. **Carrito y Pedidos**
   - Creación de pedidos
   - Historial de pedidos por usuario
   - Gestión de estado de pedidos

### Módulos Principales

#### Perfil de Usuario (`user-profile-functions.php`)

Gestiona la actualización de perfiles de usuario, incluyendo:
- Información personal (nombre, apellido, email)
- Preferencias (newsletter, género)
- Verificación de edad (restricción para menores)

```php
// Endpoint para actualizar perfil
register_rest_route('floresinc/v1', '/user/profile', array(
    'methods' => 'POST',
    'callback' => 'update_user_profile_callback',
    'permission_callback' => function () {
        return is_user_logged_in();
    }
));
```

#### Direcciones de Usuario (`user-addresses-functions.php`)

Permite a los usuarios gestionar múltiples direcciones de envío:
- Añadir, editar y eliminar direcciones
- Establecer dirección predeterminada
- Validación de campos obligatorios

```php
// Endpoint para gestionar direcciones
register_rest_route('floresinc/v1', '/user/addresses', array(
    'methods' => 'GET',
    'callback' => 'get_user_addresses_callback',
    'permission_callback' => function () {
        return is_user_logged_in();
    }
));
```

#### Integración con WooCommerce (`woocommerce-functions.php`)

Personaliza la funcionalidad de WooCommerce para:
- Adaptar la API para el frontend headless
- Personalizar el proceso de checkout
- Configurar métodos de pago y envío

```php
// Personalizar respuesta de productos
add_filter('woocommerce_rest_prepare_product_object', 'customize_product_response', 10, 3);
```

#### Páginas Legales (`legal-functions.php`)

Gestiona las páginas legales requeridas:
- Términos y condiciones
- Política de privacidad
- Política de devoluciones
- Información de envíos

```php
// Endpoint para obtener páginas legales
register_rest_route('floresinc/v1', '/legal/(?P<page>[a-zA-Z0-9-]+)', array(
    'methods' => 'GET',
    'callback' => 'get_legal_page_callback',
));
```

## Configuración de CORS (`cors-functions.php`)

Configuración para permitir solicitudes desde el frontend React:

```php
// Configurar CORS para la API
add_action('rest_api_init', function () {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function ($value) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
        return $value;
    });
});
```

## Personalización de WooCommerce

### Productos y Categorías

- Campos personalizados para productos
- Categorías destacadas
- Productos relacionados personalizados

### Proceso de Checkout

- Campos personalizados en el checkout
- Validación de datos
- Opciones de regalo

### Pedidos

- Estados personalizados de pedidos
- Notificaciones por email
- Historial de pedidos por usuario

## Requisitos

- WordPress 5.8+
- WooCommerce 6.0+
- PHP 7.4+
- MySQL 5.7+

## Instalación

1. Instalar WordPress y WooCommerce
2. Clonar o copiar este tema en la carpeta `wp-content/themes/`
3. Activar el tema desde el panel de administración
4. Configurar WooCommerce según las necesidades
5. Configurar las claves de API para la integración con el frontend

## Desarrollo

### Añadir Nuevos Endpoints

Para añadir nuevos endpoints a la API:

```php
register_rest_route('floresinc/v1', '/ruta/personalizada', array(
    'methods' => 'GET',
    'callback' => 'mi_funcion_callback',
    'permission_callback' => function () {
        return true; // O una función de verificación de permisos
    }
));
```

### Personalizar Respuestas de la API

Para personalizar las respuestas de la API de WooCommerce:

```php
add_filter('woocommerce_rest_prepare_product_object', 'mi_funcion_personalizada', 10, 3);

function mi_funcion_personalizada($response, $post, $request) {
    // Personalizar respuesta
    return $response;
}
```

## Licencia

[MIT](LICENSE)
