import { describe, expect, it } from 'vitest'
import { parseSensorSearch, resolveSensorSearch } from './search'

describe('parseSensorSearch', () => {
  it('порожній URL лишається порожнім, а resolve підставляє значення за замовчуванням з API', () => {
    expect(parseSensorSearch({})).toEqual({})
    expect(resolveSensorSearch({})).toEqual({ page: 1, page_size: 25, sort: 'created_at', order: 'desc' })
  })

  it('приймає коректні параметри', () => {
    expect(
      parseSensorSearch({
        page: 3,
        page_size: 50,
        sort: 'name',
        order: 'asc',
        name: 'CO2',
        code: 'SN-00',
        created_from: '2026-01-01',
        created_to: '2026-02-01T10:00:00',
      }),
    ).toEqual({
      page: 3,
      page_size: 50,
      sort: 'name',
      order: 'asc',
      name: 'CO2',
      code: 'SN-00',
      created_from: '2026-01-01',
      created_to: '2026-02-01T10:00:00',
    })
  })

  it('некоректні значення відкидаються (діють дефолти), а не ламають сторінку', () => {
    const bad = parseSensorSearch({ page: -5, page_size: 1000, sort: 'secret_enc', order: 'sideways' })
    expect(bad).toEqual({})
    expect(resolveSensorSearch(bad)).toMatchObject({ page: 1, page_size: 25, sort: 'created_at', order: 'desc' })
    expect(parseSensorSearch({ page: 'abc' }).page).toBeUndefined()
    expect(parseSensorSearch({ page: 2.5 }).page).toBeUndefined()
    expect(parseSensorSearch('мотлох')).toEqual({})
    // All keys are present (with undefined) so the router does not leave raw values from the URL.
    expect(Object.keys(bad)).toEqual(expect.arrayContaining(['page', 'page_size', 'sort', 'order']))
    expect(parseSensorSearch(null)).toEqual({})
  })

  it('фільтри короткі (<3 символів) або з хибною датою відкидаються', () => {
    const parsed = parseSensorSearch({ name: 'ab', code: '  ', created_from: 'вчора', created_to: '2026-13-45' })
    expect(parsed).toEqual({})
  })

  it('числоподібний код (роутер парсить JSON) лишається рядком', () => {
    expect(parseSensorSearch({ code: 12345 }).code).toBe('12345')
  })
})
