# Guía de detección: AI Content Signals y Markdown for Agents

**Versión:** 1.0  
**Fecha:** 18 de febrero de 2026  
**Autor:** Fernando Tellado  
**Destinatario:** Equipo de AgentReady.md  

---

## Contexto

Este documento detalla cómo detectar dos estándares definidos por Cloudflare para el ecosistema web + IA: **Content Signals** y **Markdown for Agents**. La información se basa en la documentación oficial de Cloudflare y en el análisis de las implementaciones actuales más relevantes en WordPress.

El objetivo es servir como referencia técnica para construir comprobaciones fiables de estos estándares en herramientas de auditoría.

**Documentación oficial de referencia:**

- Content Signals Policy: https://blog.cloudflare.com/content-signals-policy/
- Managed robots.txt (Content Signals): https://developers.cloudflare.com/bots/additional-configurations/managed-robots-txt/
- Markdown for Agents: https://developers.cloudflare.com/fundamentals/reference/markdown-for-agents/
- Anuncio Markdown for Agents: https://blog.cloudflare.com/markdown-for-agents/

---

## PARTE 1: Content Signals

### 1.1 Qué son

Los Content Signals son directivas que indican a los crawlers qué pueden hacer con el contenido **después de acceder a él**. No controlan el acceso (eso lo hacen `Allow`/`Disallow`), sino el uso posterior del contenido.

Tres señales definidas:

| Señal | Significado |
|-------|------------|
| `search=yes/no` | Indexar en buscadores y mostrar resultados (enlaces, extractos). No incluye resúmenes generados por IA. |
| `ai-input=yes/no` | Usar contenido como entrada en modelos IA (RAG, grounding, respuestas generativas). |
| `ai-train=yes/no` | Entrenar o afinar modelos de IA con el contenido. |

Una señal ausente no significa `no`; significa que el operador del sitio no expresa preferencia para ese uso concreto.

### 1.2 Dónde se implementan

Los Content Signals tienen **dos ubicaciones posibles**, con diferente contexto de aparición.

#### Ubicación primaria: robots.txt

Esta es la ubicación canónica según el estándar de Cloudflare. Se implementa como una directiva dentro de un bloque `User-Agent`.

**Formato estándar:**

```
User-Agent: *
Content-Signal: search=yes, ai-input=no, ai-train=no
Allow: /
```

**Formato con múltiples bloques por bot:**

```
User-Agent: *
Content-Signal: search=yes, ai-input=yes, ai-train=no
Allow: /

User-Agent: GPTBot
Content-Signal: search=yes, ai-input=no, ai-train=no
Allow: /
```

#### Ubicación secundaria: cabecera HTTP `Content-Signal`

Cloudflare la incluye en las **respuestas markdown** cuando el feature Markdown for Agents está activo. Es decir, aparece cuando el servidor responde con `Content-Type: text/markdown`, no en respuestas HTML estándar.

```
HTTP/2 200
content-type: text/markdown; charset=utf-8
content-signal: ai-train=yes, search=yes, ai-input=yes
```

Esta cabecera no forma parte de las respuestas HTML convencionales. Su detección corresponde al contexto de Markdown for Agents (Parte 2).

### 1.3 Cómo detectarlos

```
FUNCION detectar_content_signals(dominio):

    1. Descargar {dominio}/robots.txt

    2. Si no existe o devuelve error:
       -> resultado = "No detectado (no hay robots.txt accesible)"
       -> FIN

    3. Parsear el contenido buscando lineas "Content-Signal:"
       (puede haber varias, una por bloque User-Agent)

    4. Si se encuentran lineas Content-Signal:
       a. Extraer los pares clave=valor de cada linea
       b. Asociar cada Content-Signal con su User-Agent correspondiente
       c. Comprobar si existe el bloque de texto legal/policy (opcional)
       -> resultado = "Detectado" + detalle de señales por User-Agent
       -> FIN

    5. Si no se encuentran:
       -> resultado = "No detectado en robots.txt"
       -> FIN
```

### 1.4 Parsing del robots.txt

La directiva `Content-Signal` pertenece al bloque `User-Agent` que la precede, siguiendo la misma lógica que `Allow`/`Disallow`.

**Reglas de parsing:**

- Los valores son `yes` o `no`.
- Las señales se separan por coma y espacio.
- Una señal ausente no equivale a `no`; se debe mostrar como "sin definir" o "sin preferencia".
- Puede haber múltiples bloques con diferentes `User-Agent`, cada uno con su propio `Content-Signal`.
- El bloque `User-Agent: *` es el fallback global.
- Los bots específicos tienen prioridad sobre el comodín.

**Ejemplo de parsing:**

```
Input robots.txt:
    User-Agent: *
    Content-Signal: search=yes, ai-train=no
    Allow: /

    User-Agent: GPTBot
    Content-Signal: search=yes, ai-input=yes, ai-train=yes
    Allow: /

Output parseado:
    global (*):
        search: yes
        ai-input: (sin definir)
        ai-train: no

    GPTBot:
        search: yes
        ai-input: yes
        ai-train: yes
```

### 1.5 Detección de la Content Signals Policy (texto legal)

Opcionalmente, el robots.txt puede incluir un bloque de texto legal como comentarios. Su presencia indica una implementación más completa. El bloque comienza con:

```
# As a condition of accessing this website, you agree to abide by the
# following content signals:
```

Y contiene la definición de las señales y una referencia a la Directiva Europea 2019/790 sobre derechos de autor.

**Detección:** Buscar la cadena `"content signals"` (case insensitive) en líneas de comentario del robots.txt. Si está presente, indicarlo como "Policy incluida".

### 1.6 Implementaciones conocidas

**Cloudflare managed robots.txt:** Prepend automático del bloque con policy + señales en los más de 3,8 millones de dominios que usan el robots.txt gestionado. Valores por defecto: `search=yes, ai-train=no` (sin definir `ai-input`).

**Plugin AI Content Signals (WordPress):** Añade al robots.txt virtual de WordPress un bloque delimitado por marcadores:
```
# BEGIN AI Content Signals
...directivas...
# END AI Content Signals
```

Contiene la policy legal, señales globales y señales por bot específico. Compatible con robots.txt virtual (filtro WordPress) y opcionalmente con robots.txt físico.

**Plugin VigIA (WordPress):** No implementa Content Signals directamente. Gestiona reglas de acceso para crawlers en robots.txt y se complementa con AI Content Signals para las señales de uso.

---

## PARTE 2: Markdown for Agents

### 2.1 Qué es

Markdown for Agents es un estándar que permite servir contenido web en formato markdown optimizado para agentes IA, en lugar de HTML. Según Cloudflare, reduce el consumo de tokens en torno al 80% y mejora la calidad de interpretación por parte de LLMs.

Ref: https://developers.cloudflare.com/fundamentals/reference/markdown-for-agents/

### 2.2 Dos niveles de implementación

#### A) Nivel CDN (Cloudflare)

Cloudflare convierte HTML a markdown automáticamente en el edge cuando un cliente lo solicita vía content negotiation (`Accept: text/markdown`). No requiere cambios en el servidor origen.

Características:
- Solo funciona en sitios con Cloudflare (planes Pro, Business, Enterprise).
- No genera URLs `.md` dedicadas.
- No genera tags `<link>` ni cabeceras de descubrimiento.
- La conversión es genérica: frontmatter básico (solo title).
- Incluye cabecera `Content-Signal` en la respuesta markdown.
- Incluye cabecera `X-Markdown-Tokens` con estimación de tokens.

#### B) Nivel aplicación (WordPress u otros CMS)

El propio servidor genera markdown con metadatos enriquecidos. Implementaciones de referencia en WordPress: VigIA y Markdown Alternate (Joost de Valk).

Características de la implementación de VigIA:

1. **URLs .md directas:** `example.com/mi-post.md` devuelve markdown del contenido.
2. **Content negotiation:** `Accept: text/markdown` en URLs singulares devuelve markdown.
3. **Descubrimiento via tag HTML:** `<link rel="alternate" type="text/markdown" href="...">` en el `<head>` de cada página elegible.
4. **Descubrimiento via cabecera HTTP Link:** `Link: <url.md>; rel="alternate"; type="text/markdown"` en respuestas HTTP de páginas elegibles.
5. **YAML frontmatter enriquecido:** title, description, url, date, modified, author, image, categories, tags, type, lang.
6. **Cabeceras de respuesta markdown:**
   - `Content-Type: text/markdown; charset=utf-8`
   - `Vary: Accept`
   - `X-Markdown-Tokens: {numero}`
   - `Link: <{url_canonical}>; rel="canonical"`
7. **Integración con control de acceso:** Crawlers bloqueados reciben 403 también en URLs `.md`.
8. **Filtros de contenido:** Respeta exclusiones configuradas (noindex, patrones URL, exclusiones manuales).

### 2.3 Alcance de la detección: contenido individual

Markdown for Agents se implementa a nivel de **contenido individual** (posts, páginas, artículos), no a nivel de dominio ni de página principal. Esto se debe a que:

- La página principal de un sitio suele ser una portada dinámica o un listado, no un contenido individual con sentido como documento markdown.
- Las implementaciones WordPress solo generan markdown para post types configurados (normalmente `post` y `page`). La home no entra en esa categoría.
- Solicitar una URL `.md` de la raíz del dominio no producirá resultado porque no existe un contenido asociado.
- La content negotiation con `Accept: text/markdown` en la home no devuelve markdown porque no es contenido singular.

**Implicación para la detección:** Para comprobar Markdown for Agents es necesario analizar una URL de contenido específica (un post o página concreta), no la URL raíz del dominio.

### 2.4 Algoritmo de detección

```
FUNCION detectar_markdown_agents(dominio, url_contenido=null):

    resultados = {
        descubrimiento_link_tag: false,
        descubrimiento_link_header: false,
        content_negotiation: false,
        url_md: false,
        frontmatter: false,
        cabeceras_markdown: {},
        nivel: "no_detectado"  // cloudflare | aplicacion | no_detectado
    }

    // --- PASO 1: Obtener una URL de contenido real ---

    SI url_contenido es null:
        url_contenido = encontrar_url_contenido(dominio)
        SI url_contenido es null:
            -> resultados.nota = "Para detectar Markdown for Agents
               se necesita una URL de contenido individual (post o pagina).
               La URL raiz del dominio no aplica para esta comprobacion."
            -> RETORNAR resultados

    // --- PASO 2: Comprobar descubrimiento en la version HTML ---

    respuesta_html = GET(url_contenido)
    // Con User-Agent de navegador y Accept: text/html

    2a. Buscar en cabeceras HTTP de la respuesta:
        Link: <{url}>; rel="alternate"; type="text/markdown"
        -> Si existe: resultados.descubrimiento_link_header = true
                      resultados.url_md_descubierta = extraer_url(Link)

    2b. Buscar en el HTML del body:
        <link rel="alternate" type="text/markdown" href="{url}">
        -> Si existe: resultados.descubrimiento_link_tag = true
                      resultados.url_md_descubierta = extraer_href(link)

    // --- PASO 3: Comprobar content negotiation ---

    respuesta_neg = GET(url_contenido, headers={"Accept": "text/markdown"})

    SI respuesta_neg.status == 200:
        SI respuesta_neg.headers["Content-Type"] contiene "text/markdown":
            resultados.content_negotiation = true
            resultados.cabeceras_markdown = extraer_cabeceras(respuesta_neg)
            analizar_cuerpo_markdown(respuesta_neg.body, resultados)

    // --- PASO 4: Comprobar URL .md directa ---

    url_md = construir_url_md(url_contenido)

    // Si se descubrio una URL .md en paso 2, usar esa preferentemente
    SI resultados.url_md_descubierta:
        url_md = resultados.url_md_descubierta

    respuesta_md = GET(url_md)

    SI respuesta_md.status == 200:
        SI respuesta_md.headers["Content-Type"] contiene "text/markdown":
            resultados.url_md = true
            resultados.cabeceras_markdown = extraer_cabeceras(respuesta_md)
            analizar_cuerpo_markdown(respuesta_md.body, resultados)

    // --- PASO 5: Clasificar nivel de implementacion ---

    SI resultados.content_negotiation Y NO resultados.url_md
       Y NO resultados.descubrimiento_link_tag:
        resultados.nivel = "cloudflare"

    SI resultados.url_md O resultados.descubrimiento_link_tag
       O resultados.descubrimiento_link_header:
        resultados.nivel = "aplicacion"

    RETORNAR resultados
```

### 2.5 Funciones auxiliares

```
FUNCION encontrar_url_contenido(dominio):
    // Buscar una URL de contenido real a partir de la home o sitemap

    1. Comprobar sitemap.xml:
       GET {dominio}/sitemap.xml
       -> En WordPress puede haber sitemap index con sub-sitemaps:
          /wp-sitemap-posts-post-1.xml (posts)
          /wp-sitemap-posts-page-1.xml (paginas)
       -> Tomar la primera URL del primer sub-sitemap que no sea la home

    2. Si no hay sitemap, parsear la home:
       GET {dominio}/
       -> Buscar <article> o elementos con clases tipo "post", "entry"
       -> Extraer href del primer enlace dentro de estos contenedores
       -> Filtrar: excluir la propia home, feeds, archivos
          (/category/, /tag/, /author/, /page/)

    3. Si no se encuentra ninguna:
       -> Devolver null (la herramienta debe solicitar URL al usuario)


FUNCION construir_url_md(url_contenido):
    // Eliminar trailing slash si existe
    url = url_contenido.rstrip("/")

    // Eliminar fragment (#...) y query string (?...)
    url = url.split("#")[0].split("?")[0]

    // Agregar extension .md
    RETORNAR url + ".md"

    // Ejemplos:
    // https://ayudawp.com/vigia/       -> https://ayudawp.com/vigia.md
    // https://example.com/mi-post      -> https://example.com/mi-post.md
    // https://example.com/blog/post/   -> https://example.com/blog/post.md
    // https://example.com/2026/02/post/ -> https://example.com/2026/02/post.md


FUNCION extraer_cabeceras(respuesta):
    RETORNAR {
        content_type:      respuesta.headers["Content-Type"],
        vary:              respuesta.headers["Vary"],
        x_markdown_tokens: respuesta.headers["X-Markdown-Tokens"],
        content_signal:    respuesta.headers["Content-Signal"],
        link:              respuesta.headers["Link"]
    }


FUNCION analizar_cuerpo_markdown(body, resultados):
    // Detectar YAML frontmatter
    SI body empieza con "---\n":
        frontmatter_end = buscar("\n---\n", body, posicion=4)
        SI frontmatter_end:
            resultados.frontmatter = true
            resultados.frontmatter_campos = parsear_yaml(body[4:frontmatter_end])
```

### 2.6 Cabeceras esperadas en la respuesta markdown

| Cabecera | Descripcion | Nivel CDN (Cloudflare) | Nivel aplicacion (VigIA) |
|----------|-------------|:----------------------:|:------------------------:|
| `Content-Type: text/markdown; charset=utf-8` | Tipo de contenido | Si | Si |
| `Vary: Accept` | Variacion por cabecera Accept (caching) | Si | Si |
| `X-Markdown-Tokens: {n}` | Estimacion de tokens | Si | Si |
| `Content-Signal: {señales}` | Señales de uso del contenido | Si | No (*) |
| `Link: <{url}>; rel="canonical"` | URL canonica HTML | No | Si |

(*) VigIA no incluye `Content-Signal` como cabecera HTTP. Las señales de uso se gestionan en robots.txt mediante el plugin complementario AI Content Signals.

### 2.7 Contenido esperado del YAML frontmatter

**Implementacion nivel CDN (Cloudflare) &mdash; frontmatter basico:**

```yaml
---
title: Titulo de la pagina - Nombre del Sitio
---
```

**Implementacion nivel aplicacion (VigIA) &mdash; frontmatter enriquecido:**

```yaml
---
title: "Titulo del articulo"
description: "Extracto o resumen del contenido"
url: https://ayudawp.com/vigia/
date: 2025-08-20
modified: 2026-02-16
author: "Fernando Tellado"
image: https://ayudawp.com/wp-content/uploads/imagen.jpg
categories: ["WordPress", "Plugins"]
tags: ["AI", "Crawlers", "Markdown"]
type: page
lang: es
---
```

La presencia de frontmatter enriquecido (campos como `date`, `author`, `categories`) indica implementacion a nivel de aplicacion.

### 2.8 Mecanismos de descubrimiento

Estos mecanismos aparecen en la respuesta HTML de paginas de contenido individual. No aparecen en la home, archivos de categoria/tag/autor, ni feeds.

**Cabecera HTTP Link (en la respuesta HTML normal):**

```
Link: <https://ayudawp.com/vigia.md>; rel="alternate"; type="text/markdown"
```

**Tag HTML en el `<head>`:**

```html
<link rel="alternate" type="text/markdown" href="https://ayudawp.com/vigia.md" />
```

Ambos permiten a los agentes descubrir que existe una version markdown sin necesidad de intentar content negotiation o adivinar la URL `.md`.

---

## PARTE 3: Diferencias entre implementacion CDN y aplicacion

| Caracteristica | CDN (Cloudflare) | Aplicacion (VigIA, etc.) |
|----------------|:-----------------:|:------------------------:|
| URL `.md` directa | No | Si |
| Content negotiation (`Accept: text/markdown`) | Si | Si |
| Tag `<link rel="alternate">` en HTML | No | Si |
| Cabecera HTTP `Link: rel="alternate"` | No | Si |
| Frontmatter YAML | Basico (title) | Enriquecido (title, date, author, categories, tags, lang, image) |
| Cabecera `Content-Signal` en respuesta markdown | Si | No (va en robots.txt) |
| Cabecera `X-Markdown-Tokens` | Si | Si |
| Cabecera `Vary: Accept` | Si | Si |
| Funciona sin Cloudflare | No | Si |
| Integracion con bloqueo de crawlers | Separado (WAF/Bot Mgmt) | Integrado (403 en .md) |

---

## PARTE 4: Detalles tecnicos

### 4.1 Peticiones HTTP necesarias

**Para Content Signals (1 peticion):**

```
GET /robots.txt HTTP/1.1
Host: {dominio}
User-Agent: Mozilla/5.0 (compatible; AgentReadyChecker/1.0)
```

**Para Markdown for Agents (3 peticiones a una URL de contenido):**

```
# Peticion 1: HTML normal (para descubrimiento)
GET /vigia/ HTTP/1.1
Host: ayudawp.com
User-Agent: Mozilla/5.0 (compatible; AgentReadyChecker/1.0)
Accept: text/html

# Peticion 2: Content negotiation
GET /vigia/ HTTP/1.1
Host: ayudawp.com
User-Agent: Mozilla/5.0 (compatible; AgentReadyChecker/1.0)
Accept: text/markdown

# Peticion 3: URL .md directa
GET /vigia.md HTTP/1.1
Host: ayudawp.com
User-Agent: Mozilla/5.0 (compatible; AgentReadyChecker/1.0)
Accept: */*
```

### 4.2 Regex utiles para parsing

**Content-Signal en robots.txt:**

```regex
# Detectar linea Content-Signal (case insensitive)
/^Content-Signal:\s*(.+)$/mi

# Extraer pares clave=valor
/(search|ai-input|ai-train)\s*=\s*(yes|no)/gi

# Detectar bloque User-Agent
/^User-Agent:\s*(.+)$/mi
```

**Descubrimiento en HTML:**

```regex
# Tag <link> con alternate markdown (atributos en cualquier orden)
/<link[^>]*rel=["']alternate["'][^>]*type=["']text\/markdown["'][^>]*href=["']([^"']+)["'][^>]*\/?>/i
/<link[^>]*type=["']text\/markdown["'][^>]*rel=["']alternate["'][^>]*href=["']([^"']+)["'][^>]*\/?>/i
/<link[^>]*href=["']([^"']+)["'][^>]*rel=["']alternate["'][^>]*type=["']text\/markdown["'][^>]*\/?>/i
```

**Cabecera Link HTTP:**

```regex
# Cabecera Link con alternate markdown
/<([^>]+)>;\s*rel="alternate";\s*type="text\/markdown"/i
```

**Frontmatter YAML:**

```regex
# Detectar frontmatter (al inicio del cuerpo de la respuesta)
/^---\n([\s\S]*?)\n---/

# Extraer campos individuales
/^(title|description|date|modified|author|url|image|type|lang):\s*(.+)$/gm
/^(categories|tags):\s*\[(.+)\]$/gm
```

---

## PARTE 5: Flujo de auditoria recomendado

### 5.1 Entrada de la herramienta

La herramienta puede aceptar:

1. **Solo dominio** (ej: `ayudawp.com`) &mdash; Permite comprobar Content Signals en robots.txt. Para Markdown for Agents se intenta autodescubrir una URL de contenido.
2. **Dominio + URL de contenido** (ej: `ayudawp.com` + `https://ayudawp.com/vigia/`) &mdash; Permite comprobacion completa de ambos estandares.

**Sugerencia de UX:** Si el usuario introduce solo un dominio, ofrecer:
- Autodescubrimiento automatico (analizar sitemap o home para localizar una URL de contenido).
- Campo adicional para que el usuario introduzca una URL de contenido manualmente.
- Si no se obtiene URL de contenido, indicar que Markdown for Agents requiere una URL especifica para su comprobacion, sin marcarlo como "no detectado".

### 5.2 Secuencia de comprobaciones

```
1. CONTENT SIGNALS
   |-> GET {dominio}/robots.txt
   |-> Parsear Content-Signal por User-Agent
   |-> Detectar Content Signals Policy (texto legal)
   |-> Resultado: Detectado / No detectado + detalle

2. MARKDOWN FOR AGENTS
   |-> 2a. Obtener URL de contenido (proporcionada o autodescubierta)
   |   |-> Si no se obtiene: informar, no marcar como "no detectado"
   |-> 2b. Comprobar descubrimiento (GET HTML de la URL de contenido)
   |   |-> Cabecera Link con rel="alternate" type="text/markdown"
   |   |-> Tag <link rel="alternate" type="text/markdown"> en HTML
   |-> 2c. Comprobar content negotiation (GET con Accept: text/markdown)
   |   |-> Verificar Content-Type: text/markdown en respuesta
   |-> 2d. Comprobar URL .md directa
   |   |-> Verificar que devuelve 200 con Content-Type: text/markdown
   |-> 2e. Analizar calidad de la respuesta markdown
   |   |-> Frontmatter YAML presente y campos
   |   |-> Cabecera X-Markdown-Tokens presente
   |   |-> Cabecera Vary: Accept presente
   |   |-> Content-Signal en cabecera HTTP (si es Cloudflare)

3. RESULTADO COMBINADO
   |-> Mostrar estado de cada comprobacion individualmente
```

### 5.3 Ejemplos de presentacion de resultados

**Content Signals detectados:**

```
[+] Content Signals detectados en robots.txt
    - Global (*): search=yes, ai-input=yes, ai-train=no
    - GPTBot: search=yes, ai-input=no, ai-train=no
    - Policy legal: Incluida
```

**Content Signals no detectados:**

```
[-] Content Signals no detectados
    - No se encontraron directivas Content-Signal en robots.txt
```

**Markdown for Agents detectado (nivel aplicacion):**

```
[+] Markdown for Agents detectado (nivel aplicacion)
    - URL .md directa: Si - https://ayudawp.com/vigia.md (200 OK)
    - Content negotiation: Si (Accept: text/markdown -> 200 text/markdown)
    - Descubrimiento Link header: Si
    - Descubrimiento <link> tag: Si
    - Frontmatter YAML: Si (title, date, author, categories, tags, lang)
    - X-Markdown-Tokens: Si (3150)
    - Vary: Accept: Si
```

**Markdown for Agents detectado (nivel CDN):**

```
[+] Markdown for Agents detectado (nivel CDN / Cloudflare)
    - Content negotiation: Si (Accept: text/markdown -> 200 text/markdown)
    - URL .md directa: No disponible
    - Descubrimiento Link header: No disponible
    - Descubrimiento <link> tag: No disponible
    - Frontmatter YAML: Basico (title)
    - X-Markdown-Tokens: Si (725)
    - Content-Signal header: Si (ai-train=yes, search=yes, ai-input=yes)
    - Vary: Accept: Si
```

**Markdown for Agents pendiente de comprobacion:**

```
[?] Markdown for Agents: pendiente
    - Para esta comprobacion se necesita una URL de contenido individual
      (post o pagina). La URL raiz del dominio no aplica.
      [Introducir URL de contenido] [Autodescubrir]
```

---

## PARTE 6: Casos de prueba

### 6.1 Sitio con ambos estandares implementados (WordPress)

**Entrada:** `ayudawp.com` + URL: `https://ayudawp.com/vigia/`

**Resultado esperado de Content Signals:**
- Detectados en robots.txt con señales globales y por bot

**Resultado esperado de Markdown for Agents:**
- Content negotiation: detectado
- URL .md: detectado (`https://ayudawp.com/vigia.md`)
- Link header: detectado
- Link tag: detectado
- Frontmatter: enriquecido
- X-Markdown-Tokens: presente
- Nivel: aplicacion

### 6.2 Sitio con Cloudflare Markdown for Agents

**Entrada:** `blog.cloudflare.com` + URL: `https://blog.cloudflare.com/markdown-for-agents/`

**Resultado esperado:**
- Content negotiation: detectado
- URL .md: no disponible
- Link header/tag: no disponible
- Frontmatter: basico (title)
- Content-Signal header: detectado en la respuesta markdown
- X-Markdown-Tokens: presente
- Nivel: CDN

### 6.3 Sitio sin implementacion

**Entrada:** cualquier sitio sin estos estandares

**Resultado esperado:**
- Content Signals: no detectados en robots.txt
- Markdown for Agents: no detectado (ni content negotiation ni .md URLs responden con markdown)

### 6.4 Solo dominio sin URL de contenido

**Entrada:** `example.com` (solo dominio)

**Resultado esperado:**
- Content Signals: comprobacion normal en robots.txt
- Markdown for Agents: intentar autodescubrimiento; si no se encuentra URL, indicar que se necesita una URL de contenido (no marcar como "no detectado")

---

## Resumen

1. **Content Signals se definen en robots.txt.** Es la ubicacion canonica segun Cloudflare. La cabecera HTTP `Content-Signal` solo aparece en respuestas markdown.

2. **Markdown for Agents opera a nivel de contenido individual.** La deteccion requiere analizar una URL de post o pagina concreta, no la raiz del dominio.

3. **Señal ausente no es lo mismo que señal negada.** Si `ai-input` no esta definido, se debe mostrar como "sin preferencia expresada".

4. **Existen dos niveles de implementacion** (CDN y aplicacion) con capacidades distintas. Ambos son validos y deben detectarse por separado.

5. **Hay cuatro mecanismos de deteccion para markdown:** URL .md, content negotiation, cabecera HTTP Link, y tag `<link>` en HTML. Cada uno es independiente.

6. **La URL .md se construye** eliminando el trailing slash de la URL del contenido y añadiendo `.md`.

7. **Si no se puede comprobar Markdown for Agents** por falta de URL de contenido, se debe indicar como "pendiente de comprobacion", no como "no detectado".
