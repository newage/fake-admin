// API response types in one place; the contract is docs/api.md.

export interface Sensor {
  id: number
  code: string
  name: string
  type: string
  unit: string
  device_id: string
  location: string
  status: string
  created_at: string
  last_seen_at: string | null
}

export interface SensorsPage {
  items: Sensor[]
  total: number
  page: number
  page_size: number
}
