export type ContractError = {
    error?: {
        code?: string
        message?: string
        details?: { fields?: Record<string, string[]> }
    }
}

export async function authRequest(path: string, body: Record<string, unknown>, method = 'POST', extraHeaders: Record<string, string> = {}) {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
    const response = await fetch(path, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
            ...extraHeaders,
        },
        body: JSON.stringify(body),
    })

    const payload = response.status === 204 ? {} : (await response.json()) as ContractError & { data?: Record<string, unknown> }
    return { response, payload }
}

/** A fresh client-generated value for the `Idempotency-Key` header on a REQUIRED write. */
export function newIdempotencyKey(): string {
    return typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `key-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

export async function authFormRequest(path: string, body: FormData, method = 'POST') {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
    const response = await fetch(path, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
        },
        body,
    })

    const payload = response.status === 204 ? {} : (await response.json()) as ContractError & { data?: Record<string, unknown> }
    return { response, payload }
}

export function errorText(payload: ContractError): string {
    return payload.error?.message ?? 'Permintaan tidak dapat diproses. Silakan coba kembali.'
}
