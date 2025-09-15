# 🎲 Portal de los Números Afortunados

## 🔧 Configuración del Proyecto

### 📁 Estructura de Archivos
- `config.example.php`: Plantilla de configuración
- `config.php`: Archivo de configuración (no versionado)
- `environment`: Define el entorno actual (desarrollo/producción)
- `conexion.php`: Gestión de conexión a la base de datos
- `.gitignore`: Configuración de archivos ignorados

### 🌍 Gestión de Entornos

El proyecto utiliza un sistema de entornos dual:

1. **Archivo `environment`**:
   - Contiene una única palabra: `desarrollo` o `produccion`
   - Este archivo SÍ se versiona en Git
   - Determina qué credenciales se usarán en el deploy

```bash
# Cambiar a modo desarrollo
echo "desarrollo" > environment
git add environment
git commit -m "Cambiar a modo desarrollo"
git push

# Cambiar a modo producción
echo "produccion" > environment
git add environment
git commit -m "Cambiar a modo producción"
git push
```

### 🔐 Secrets de GitHub Actions

Los secrets están configurados en el repositorio y se usan automáticamente:

#### Producción
```
DB_HOST: localhost
DB_NAME: estavi0_sheerit
DB_USER: estavi0_sheerit
DB_PASS: [Seguro]
BOLD_IDENTITY_KEY: [Llave de Producción]
BOLD_SECRET_KEY: [Llave de Producción]
```

#### Desarrollo
```
DEV_BOLD_IDENTITY_KEY: [Llave de Desarrollo]
DEV_BOLD_SECRET_KEY: [Llave de Desarrollo]
```

### 🚀 Despliegue Automático

El workflow de GitHub Actions:
1. Lee el archivo `environment`
2. Genera `config.php` con las credenciales correctas
3. Despliega vía FTP al servidor

## 🛠️ Guía de Desarrollo

### Configuración Inicial
1. Clona el repositorio
2. Copia `config.example.php` a `config.php`
3. Configura el entorno:
   ```bash
   echo "desarrollo" > environment
   ```
4. Actualiza las credenciales en `config.php`

### Cambio de Entorno
- El archivo `environment` determina qué credenciales se usarán
- Cambia su contenido a `desarrollo` o `produccion` según necesites
- Commitea y pushea el cambio para que surta efecto

### ⚠️ Advertencias
- NUNCA subas `config.php` al repositorio
- NUNCA expongas las credenciales en el código
- Siempre verifica el contenido de `environment` antes de hacer push

## 🔒 Seguridad

- Las credenciales se manejan como secrets en GitHub
- El archivo `config.php` está en `.gitignore`
- El archivo `environment` es el único que determina el entorno
- Las llaves de Bold están separadas por entorno

## 📝 Workflow de Desarrollo

1. Clona el repositorio
2. Configura el entorno local
3. Desarrolla y prueba con `environment` en modo `desarrollo`
4. Para producción:
   - Cambia `environment` a `produccion`
   - Commitea y pushea
   - El deploy se hará automáticamente

## 🆘 Solución de Problemas

Si las credenciales no se aplican correctamente:
1. Verifica el contenido del archivo `environment`
2. Confirma que los secrets estén configurados en GitHub
3. Revisa los logs del workflow en GitHub Actionsos Números Afortunados

> *"En el reino de los números, cada secuencia cuenta una historia..."*

## �️ El Grimorio de las Configuraciones

Los antiguos textos hablan de un archivo llamado `config.example.php`, un espejo del verdadero grimorio de poder. Para desbloquear sus secretos, deberás emprender un viaje por las profundidades del código.

### 🌘 Las Fases de la Luna

```
Desarrollo ⟶ Luna Nueva
Producción ⟶ Luna Llena
```

### � Los Pergaminos Antiguos

#### El Manuscrito del Desarrollo
```
TmF2ZWdhbnRlLCBzaSBidXNjYXMgbGFzIGxsYXZlcyBkZWwgcmVpbm8sIHNhYmUgcXVlIGVsIG1lbnNhamVybyBhdWRheiBlc2NvbmRpw7Mgc3VzIHNlY3JldG9zIGVuIGVsIGJvc3F1ZSBkZSBsb3MgNjQgw6FyYm9sZXMuIFNvbG8gYXF1ZWxsb3MgcXVlIGNvbm96Y2FuIGxhIGRhbnphIGRlIGxvcyBiaXRzIHBvZHLDoW4gZGVzY2lmcmFyIGVsIGNhbWluby4=
```

#### El Manuscrito de la Producción
```
RW4gbGEgY2l1ZGFkIGRlIGxvcyBuw7ptZXJvcywgZG9uZGUgbGFzIGVzdHJlbGxhcyBicmlsbGFuIGNvbiBlbCByZXNwbGFuZG9yIGRlIDY0IHNvbGVzLCBzZSBlc2NvbmRlbiB0ZXNvcm9zIHF1ZSBzb2xvIGxvcyB2ZXJkYWRlcm9zIGd1YXJkaWFuZXMgcHVlZGVuIGRlc2NpZnJhci4=
```

### � La Secuencia del Destino

```
Nivel 1: Los Susurros del Viento
Nivel 2: El Eco de los Bytes
Nivel 3: La Danza de los Caracteres
Nivel 4: El Despertar del Código
```

### 🌟 Los Astros Guardianes

```
⭐ El Guardián del Norte: YmFzZTY0X2RlY29kZQ==
⭐ El Guardián del Sur: c3Ryb3RyMTM=
⭐ El Guardián del Este: cGhw
⭐ El Guardián del Oeste: ZWNo
```

## 🎮 La Partida

1. Invoca el espejo del grimorio
2. Descifra los manuscritos antiguos
3. Sigue la secuencia del destino
4. Consulta a los astros guardianes
5. El resto del camino se revelará ante ti...

## � Los Artefactos del Sistema

- `🪞 config.example.php` - El espejo de las posibilidades
- `🔮 conexion.php` - El orbe de conexión
- `⚡ generar_token.php` - El generador de energía
- `💫 procesar-pago.php` - El canalizador de fortunas
- `📡 procesar-webhook.php` - El receptor de señales cósmicas

## ⚠️ Las Advertencias del Oráculo

*"Aquel que comparta los secretos del grimorio enfrentará la ira de los mil bugs..."*

- Mantén el grimorio (`config.php`) oculto de ojos curiosos
- Las runas de producción solo deben ser invocadas en el templo sagrado
- Protege los sellos con tu vida

## 🎭 La Hermandad del Código

Para unirte a la hermandad, debes demostrar tu valía descifrando estos antiguos textos. 
Los verdaderos iniciados sabrán qué hacer con estos fragmentos de conocimiento.

*"El conocimiento es poder, pero el verdadero poder yace en saber cuándo usarlo..."*