import { createRoute } from '@tanstack/react-router'
import { SensorsPage } from '@/features/sensors/sensors-page'
import { parseSensorSearch } from '@/features/sensors/search'
import { appRoute } from './app'

export const sensorsRoute = createRoute({
  getParentRoute: () => appRoute,
  path: '/sensors',
  validateSearch: parseSensorSearch,
  component: function Sensors() {
    return <SensorsPage params={sensorsRoute.useSearch()} />
  },
})
