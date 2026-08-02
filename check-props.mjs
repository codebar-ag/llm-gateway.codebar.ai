/**
 * Fails when a page passes a prop the design system does not declare.
 *
 * Vue silently renders an unknown prop as a plain HTML attribute on the
 * component's root element, so a typo binds nothing and still builds, still
 * passes every PHP test, and still renders — just without the value. Two such
 * bugs shipped during the Inertia migration: `DescriptionItem :value` (the
 * value belongs in the default slot) blanked the whole entry summary, and
 * `FormGrid :columns` (the prop is `cols`) silently left the form at its
 * two-column default.
 *
 * Props are read from the package's own `index.d.ts`, so this tracks the
 * installed version rather than a copy that would drift.
 */
import { readFileSync, readdirSync } from 'node:fs'
import { join, relative } from 'node:path'

const DTS = 'node_modules/@codebar-ag/storybook/dist/index.d.ts'
const PAGES = 'resources/js'

/** Attributes that are meaningful on any component. Everything else must be
 *  a declared prop, or fall through to a matching native element. */
const GLOBAL = new Set(['class', 'style', 'id', 'key', 'ref', 'slot'])

/** Components whose root is a native element the listed attributes belong to,
 *  where fallthrough is the intended behaviour rather than a mistake. */
/*
 | Attributes a component does not declare but is still right to receive,
 | because its root really is the native element they belong to and Vue's
 | fallthrough puts them there.
 |
 | `Button` renders a native <button>, so `aria-label` lands on it. It is worth
 | the exception: two of these atoms carry the same caption in different states
 | ("Name" / "Rename"), and a label is what tells a screen reader — and a test —
 | which control it has hold of without matching on that caption.
 */
const FALLTHROUGH = {
    Input: ['placeholder', 'autocomplete', 'autofocus', 'inputmode', 'min', 'max', 'step'],
    // Both spellings: the scanner reads `:aria-label` off the template as the
    // camelCase `ariaLabel`, while a plain `aria-label="…"` stays hyphenated.
    Button: ['aria-label', 'ariaLabel'],
}

const dts = readFileSync(DTS, 'utf8')

/*
 | An exported component reaches its props through a chain of internal aliases,
 | and the shape of that chain depends on how the types were bundled:
 |
 |   vite-plugin-dts 4:  X -> __VLS_WithTemplateSlots_N<typeof __VLS_component_N>
 |                            -> DefineComponent<__VLS_Props_M>
 |   vite-plugin-dts 5:  X -> __VLS_export_N -> __VLS_WithSlots_M<typeof __VLS_base_M>
 |                            -> DefineComponent<__VLS_Props_K>
 |
 | So rather than matching either spelling, follow `typeof` references until a
 | DefineComponent turns up. Hard-coding one shape is what made this silently
 | index zero components — and pass every file — after an upgrade.
 */
/*
 | Resolved per symbol over a bounded window rather than from a map of whole
 | declarations: a `DefineComponent<…>` spans dozens of lines and its type
 | arguments contain `;`, so there is no cheap terminator to split declarations
 | on. Looking each symbol up where it is declared sidesteps that entirely.
 */
/**
 * One declaration, from `index` up to where the next top-level one begins.
 *
 * A fixed-size window is wrong here: exports are one-liners packed together, so
 * a few hundred characters spills into the next component and props get read
 * off a neighbour. Every declaration in this file starts at column 0, which
 * makes the next one the only boundary that is actually correct.
 */
function statementAt(index) {
    const from = dts.slice(index)
    const boundary = from.match(/\n(?:export )?declare /)

    return boundary ? from.slice(0, boundary.index) : from
}

function propsTypeIn(text, seen) {
    const direct = text.match(/DefineComponent<\s*(__VLS_Props\w*)\b/)
    if (direct) return direct[1]

    for (const [, symbol] of text.matchAll(/typeof (\w+)/g)) {
        if (seen.has(symbol)) continue
        seen.add(symbol)

        const at = dts.indexOf(`declare const ${symbol}:`)
        if (at === -1) continue

        const found = propsTypeIn(statementAt(at), seen)
        if (found) return found
    }

    return null
}

const propsOf = (type) => {
    const m = dts.match(new RegExp(`declare type ${type} = \\{([\\s\\S]*?)\\n\\};`))
    if (!m) return null
    const body = m[1].replace(/\/\*[\s\S]*?\*\//g, '')
    return new Set([...body.matchAll(/^\s*([a-zA-Z]\w*)\??\s*:/gm)].map((x) => x[1]))
}

const components = new Map()
for (const m of dts.matchAll(/export declare const (\w+):/g)) {
    const type = propsTypeIn(statementAt(m.index), new Set())
    if (type) components.set(m[1], propsOf(type))
}

const camel = (n) => n.replace(/-([a-z])/g, (_, c) => c.toUpperCase())

/** Attribute names only — quoted values are blanked first so prose inside a
 *  `hint="…"` cannot be mistaken for a prop. */
const attributesIn = (tag) =>
    [...tag.replace(/=\s*"[^"]*"/g, '=""').replace(/=\s*'[^']*'/g, "=''")
        .matchAll(/(?:^|\s)(?::|v-bind:)?([a-zA-Z][\w-]*)(?=\s*=|\s|$)/g)]
        .map((m) => m[1])
        .filter((n) => !n.startsWith('v-') && !n.startsWith('@') && !n.startsWith('#'))
        .map(camel)

/*
 | A checker that indexes nothing reports every file as clean, which is worse
 | than having no checker at all — it is a green light that means nothing. This
 | exact thing happened once: a `vite-plugin-dts` upgrade changed how the
 | package bundles its declarations, and the parse above quietly matched zero
 | components while still exiting 0.
 */
const MIN_COMPONENTS = 40

if (components.size < MIN_COMPONENTS) {
    console.error(
        `check-props: only indexed ${components.size} components from ${DTS}, expected at least ${MIN_COMPONENTS}.\n`
        + 'The package almost certainly changed how it emits declarations. Fix the\n'
        + 'parser rather than lowering this number — until then every prop is unchecked.',
    )
    process.exit(1)
}

const vueFiles = readdirSync(PAGES, { recursive: true, encoding: 'utf8' })
    .filter((f) => f.endsWith('.vue'))
    .map((f) => join(PAGES, f))

let failures = 0
for (const file of vueFiles.sort()) {
    const src = readFileSync(file, 'utf8')
    const template = src.slice(src.indexOf('<template>'), src.lastIndexOf('</template>'))

    for (const m of template.matchAll(/<([A-Z]\w+)((?:[^>"']|"[^"]*"|'[^']*')*?)\/?>/g)) {
        const [, name, body] = m
        const declared = components.get(name)
        if (!declared) continue

        const allowed = new Set([...declared, ...GLOBAL, ...(FALLTHROUGH[name] ?? [])])
        for (const attribute of attributesIn(body)) {
            if (allowed.has(attribute)) continue
            const known = [...declared].sort().join(', ') || 'no props'
            console.error(`${relative('.', file)}: <${name}> got "${attribute}" — declares {${known}}`)
            failures++
        }
    }
}

console.log(
    failures === 0
        ? `check-props: ${vueFiles.length} files clean against ${components.size} components`
        : `check-props: ${failures} undeclared prop(s)`,
)
process.exit(failures === 0 ? 0 : 1)
