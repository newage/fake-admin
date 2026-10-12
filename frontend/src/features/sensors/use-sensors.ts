import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { apiFetch } from '@/lib/api-client'
import type { SensorsPage } from '@/lib/api-types'
import type { SensorSearch } from './search'

export function useSensors(search: SensorSearch) {
  return useQuery({
    queryKey: ['sensors', search],
    queryFn: () => {
      const params = new URLSearchParams()
      for (const [key, value] of Object.entries(search)) {
        if (value !== undefined) params.set(key, String(value))
      }
      return apiFetch<SensorsPage>(`/api/sensors?${params}`)
    },
    placeholderData: keepPreviousData,
  })
}
