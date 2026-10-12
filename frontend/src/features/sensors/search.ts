import { z } from 'zod'

export const PAGE_SIZES = [10, 25, 50, 100] as const
export const SORT_FIELDS = ['name', 'code', 'created_at'] as const

export const MIN_FILTER_LENGTH = 3

const DATE_RE = /^(\d{4})-(\d{2})-(\d{2})(T(\d{2}):(\d{2}):(\d{2}))?$/

function isApiDate(value: string) {
  const m = DATE_RE.exec(value)
  if (!m) return false
  const [y, mo, d] = [Number(m[1]), Number(m[2]), Number(m[3])]
  const date = new Date(Date.UTC(y, mo - 1, d))
  const validDay = date.getUTCFullYear() === y && date.getUTCMonth() === mo - 1 && date.getUTCDate() === d
  return validDay && (m[5] === undefined || (Number(m[5]) < 24 && Number(m[6]) < 60 && Number(m[7]) < 60))
}

// The router parses URL values as JSON, so "12345" arrives as a number.
const prefix = z.coerce.string().trim().min(MIN_FILTER_LENGTH).optional().catch(undefined)
const date = z.coerce.string().refine(isApiDate).optional().catch(undefined)

export const sensorSearchSchema = z.object({
  page: z.number().int().min(1).optional().catch(undefined),
  page_size: z.number().int().min(1).max(100).optional()
    .catch(undefined),
  sort: z.enum(SORT_FIELDS).optional().catch(undefined),
  order: z.enum(['asc', 'desc']).optional().catch(undefined),
  name: prefix,
  code: prefix,
  created_from: date,
  created_to: date,
})

/** What lives in the URL: everything optional, invalid values dropped. */
export type SensorSearchParams = z.infer<typeof sensorSearchSchema>

/** Params with defaults filled in (as in the API). */
export type SensorSearch = SensorSearchParams & {
  page: number
  page_size: number
  sort: (typeof SORT_FIELDS)[number]
  order: 'asc' | 'desc'
}

/** `validateSearch` for the route: invalid params are dropped rather than breaking the page. */
export function parseSensorSearch(raw: unknown): SensorSearchParams {
  const input = typeof raw === 'object' && raw !== null ? raw : {}
  // Keys with undefined stay: the router merges raw URL params with the validateSearch result,
  // so an omitted key would leave the invalid value from the URL.
  return sensorSearchSchema.parse(input)
}

export function resolveSensorSearch(params: SensorSearchParams): SensorSearch {
  return { page: 1, page_size: 25, sort: 'created_at', order: 'desc', ...stripUndefined(params) }
}

function stripUndefined<T extends object>(obj: T): T {
  return Object.fromEntries(Object.entries(obj).filter(([, v]) => v !== undefined)) as T
}

export const FILTER_KEYS = ['name', 'code', 'created_from', 'created_to'] as const

export function hasFilters(search: SensorSearchParams) {
  return FILTER_KEYS.some((k) => search[k] !== undefined)
}
