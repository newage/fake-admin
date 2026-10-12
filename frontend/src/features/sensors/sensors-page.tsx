import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from '@tanstack/react-router'
import {
  flexRender,
  getCoreRowModel,
  useReactTable,
  type ColumnDef,
  type SortingState,
} from '@tanstack/react-table'
import { useTranslation } from 'react-i18next'
import { Activity, ArrowDown, ArrowUp, ArrowUpDown, SlidersHorizontal } from 'lucide-react'
import { ApiError } from '@/lib/api-client'
import { useDateFormatter } from '@/lib/i18n'
import type { Sensor } from '@/lib/api-types'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import {
  MIN_FILTER_LENGTH,
  PAGE_SIZES,
  SORT_FIELDS,
  hasFilters,
  resolveSensorSearch,
  type SensorSearch,
  type SensorSearchParams,
} from './search'
import { useSensors } from './use-sensors'

function useColumns(): ColumnDef<Sensor>[] {
  const { t } = useTranslation()
  const formatDate = useDateFormatter()
  return useMemo(
    () => [
      { accessorKey: 'code', header: t('sensors.columns.code') },
      { accessorKey: 'name', header: t('sensors.columns.name') },
      { accessorKey: 'type', header: t('sensors.columns.type'), enableSorting: false },
      { accessorKey: 'location', header: t('sensors.columns.location'), enableSorting: false },
      {
        accessorKey: 'status',
        header: t('sensors.columns.status'),
        enableSorting: false,
        cell: ({ getValue }) => (
          <span className="inline-flex items-center gap-2 rounded-full bg-accent-soft px-3 py-1 text-xs text-accent-foreground">
            <span className="size-1.5 rounded-full bg-accent-foreground" aria-hidden />
            {String(getValue())}
          </span>
        ),
      },
      {
        accessorKey: 'created_at',
        header: t('sensors.columns.created_at'),
        cell: ({ getValue }) => formatDate(getValue<string>()),
      },
      {
        accessorKey: 'last_seen_at',
        header: t('sensors.columns.last_seen_at'),
        enableSorting: false,
        cell: ({ getValue }) => formatDate(getValue<string | null>()),
      },
    ],
    [t, formatDate],
  )
}

type TextFilter = 'name' | 'code'

/** Prefix filter field: only an empty value or one of 3+ characters reaches the URL. */
function PrefixFilter({
  field,
  label,
  value,
  onCommit,
}: {
  field: TextFilter
  label: string
  value: string | undefined
  onCommit: (field: TextFilter, value: string | undefined) => void
}) {
  const { t } = useTranslation()
  const [draft, setDraft] = useState(value ?? '')
  const committed = useRef(value)

  // An external URL change (reset, back/forward) updates the field.
  useEffect(() => {
    if (value !== committed.current) {
      committed.current = value
      setDraft(value ?? '')
    }
  }, [value])

  useEffect(() => {
    const next = draft.trim()
    const target = next.length >= MIN_FILTER_LENGTH ? next : next.length === 0 ? undefined : committed.current
    if (target === committed.current) return
    const t = setTimeout(() => {
      committed.current = target
      onCommit(field, target)
    }, 400)
    return () => clearTimeout(t)
  }, [draft, field, onCommit])

  const short = draft.trim().length > 0 && draft.trim().length < MIN_FILTER_LENGTH
  return (
    <div className="flex flex-col gap-2">
      <Label htmlFor={`filter-${field}`}>{label}</Label>
      <Input
        id={`filter-${field}`}
        value={draft}
        onChange={(e) => setDraft(e.target.value)}
        placeholder={t('sensors.filters.placeholder', { count: MIN_FILTER_LENGTH })}
        aria-describedby={short ? `filter-${field}-hint` : undefined}
      />
      {short && (
        <p id={`filter-${field}-hint`} className="text-xs text-muted-foreground">
          {t('sensors.filters.min', { count: MIN_FILTER_LENGTH })}
        </p>
      )}
    </div>
  )
}

export function SensorsPage({ params }: { params: SensorSearchParams }) {
  const { t } = useTranslation()
  const columns = useColumns()
  const search = resolveSensorSearch(params)
  const navigate = useNavigate({ from: '/sensors' })
  const query = useSensors(search)

  // Any change of filters, sorting or page size returns to the first page.
  function update(patch: Partial<SensorSearchParams>, { keepPage = false } = {}) {
    void navigate({
      search: (prev) => ({ ...prev, ...(keepPage ? {} : { page: 1 }), ...patch }),
    })
  }

  const commitFilter = (field: TextFilter, value: string | undefined) => update({ [field]: value })
  const resetFilters = () =>
    void navigate({
      search: (prev) => ({ page_size: prev.page_size, sort: prev.sort, order: prev.order }),
    })

  const sorting: SortingState = [{ id: search.sort, desc: search.order === 'desc' }]
  const data = query.data
  const table = useReactTable({
    data: data?.items ?? [],
    columns,
    state: { sorting },
    manualPagination: true,
    manualSorting: true,
    manualFiltering: true,
    enableSortingRemoval: false,
    getCoreRowModel: getCoreRowModel(),
    onSortingChange: (updater) => {
      const next = (typeof updater === 'function' ? updater(sorting) : updater)[0]
      if (next && (SORT_FIELDS as readonly string[]).includes(next.id)) {
        update({ sort: next.id as SensorSearch['sort'], order: next.desc ? 'desc' : 'asc' })
      }
    },
  })

  const pageCount = data ? Math.max(1, Math.ceil(data.total / data.page_size)) : 1
  const filtered = hasFilters(params)

  if (query.error instanceof ApiError && query.error.status === 403) {
    return (
      <Card className="p-[var(--space-card)]">
        <ErrorState error={query.error} onRetry={() => void query.refetch()} />
      </Card>
    )
  }

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-3xl font-medium">{t('sensors.title')}</h1>

      <Card className="p-[var(--space-card)]">
        <div className="mb-5 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <span className="grid size-8 place-items-center rounded-md bg-accent text-accent-foreground">
              <SlidersHorizontal className="size-4" aria-hidden />
            </span>
            <h2 className="text-xl">{t('sensors.filters.title')}</h2>
          </div>
          <Button variant="link" size="sm" onClick={resetFilters} disabled={!filtered}>
            {t('sensors.filters.clear')}
          </Button>
        </div>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <PrefixFilter field="name" label={t('sensors.filters.name')} value={search.name} onCommit={commitFilter} />
          <PrefixFilter field="code" label={t('sensors.filters.code')} value={search.code} onCommit={commitFilter} />
          <div className="flex flex-col gap-2">
            <Label htmlFor="filter-created_from">{t('sensors.filters.created_from')}</Label>
            <Input
              id="filter-created_from"
              type="date"
              value={search.created_from?.slice(0, 10) ?? ''}
              onChange={(e) => update({ created_from: e.target.value || undefined })}
            />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="filter-created_to">{t('sensors.filters.created_to')}</Label>
            <Input
              id="filter-created_to"
              type="date"
              value={search.created_to?.slice(0, 10) ?? ''}
              onChange={(e) => update({ created_to: e.target.value || undefined })}
            />
          </div>
        </div>
      </Card>

      <Card className="p-[var(--space-card)]">
        <div className="mb-5 flex items-center gap-3">
          <span className="grid size-8 place-items-center rounded-md bg-accent text-accent-foreground">
            <Activity className="size-4" aria-hidden />
          </span>
          <h2 className="text-xl">{t('sensors.list.title')}</h2>
          {data && <span className="text-xs text-muted-foreground">{t('sensors.list.total', { total: data.total })}</span>}
        </div>

        {query.isError ? (
          <ErrorState error={query.error} onRetry={() => void query.refetch()} />
        ) : data === undefined ? (
          <p className="py-12 text-center text-muted-foreground" role="status">
            {t('sensors.list.loading')}
          </p>
        ) : data.total === 0 ? (
          <div className="flex flex-col items-center gap-4 py-12 text-center">
            <p>{filtered ? t('sensors.list.not_found') : t('sensors.list.empty')}</p>
            {filtered && (
              <Button variant="outline" onClick={resetFilters}>
                {t('sensors.list.reset_filters')}
              </Button>
            )}
          </div>
        ) : data.items.length === 0 ? (
          <div className="flex flex-col items-center gap-4 py-12 text-center">
            <p>{t('sensors.list.page_empty')}</p>
            <Button variant="outline" onClick={() => update({ page: 1 }, { keepPage: true })}>
              {t('sensors.list.first_page')}
            </Button>
          </div>
        ) : (
          <div className={query.isPlaceholderData ? 'opacity-60' : undefined}>
            <div className="overflow-x-auto">
            <Table>
              <TableHeader>
                {table.getHeaderGroups().map((group) => (
                  <TableRow key={group.id}>
                    {group.headers.map((header) => {
                      const dir = header.column.getIsSorted()
                      const Icon = dir === 'asc' ? ArrowUp : dir === 'desc' ? ArrowDown : ArrowUpDown
                      return (
                        <TableHead
                          key={header.id}
                          aria-sort={dir === 'asc' ? 'ascending' : dir === 'desc' ? 'descending' : undefined}
                        >
                          {header.column.getCanSort() ? (
                            <button
                              type="button"
                              className="inline-flex items-center gap-1.5"
                              onClick={header.column.getToggleSortingHandler()}
                            >
                              {flexRender(header.column.columnDef.header, header.getContext())}
                              <Icon className="size-3.5 text-muted-foreground" aria-hidden />
                            </button>
                          ) : (
                            flexRender(header.column.columnDef.header, header.getContext())
                          )}
                        </TableHead>
                      )
                    })}
                  </TableRow>
                ))}
              </TableHeader>
              <TableBody>
                {table.getRowModel().rows.map((row) => (
                  <TableRow key={row.id}>
                    {row.getVisibleCells().map((cell) => (
                      <TableCell key={cell.id}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</TableCell>
                    ))}
                  </TableRow>
                ))}
              </TableBody>
            </Table>
            </div>

            <div className="mt-5 flex flex-wrap items-center justify-between gap-4 text-sm">
              <label className="flex items-center gap-2 text-muted-foreground">
                {t('sensors.pagination.per_page')}
                <select
                  className="h-9 rounded-md border bg-card px-2 text-foreground"
                  value={search.page_size}
                  onChange={(e) => update({ page_size: Number(e.target.value) })}
                >
                  {[...new Set([...PAGE_SIZES, search.page_size])].sort((a, b) => a - b).map((n) => (
                    <option key={n} value={n}>
                      {n}
                    </option>
                  ))}
                </select>
              </label>
              <div className="flex items-center gap-3">
                <span className="text-muted-foreground">
                  {t('sensors.pagination.page', { page: data.page, pages: pageCount })}
                </span>
                <Button
                  variant="outline"
                  size="sm"
                  disabled={search.page <= 1}
                  onClick={() => update({ page: search.page - 1 }, { keepPage: true })}
                >
                  {t('sensors.pagination.prev')}
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  disabled={search.page >= pageCount}
                  onClick={() => update({ page: search.page + 1 }, { keepPage: true })}
                >
                  {t('sensors.pagination.next')}
                </Button>
              </div>
            </div>
          </div>
        )}
      </Card>
    </div>
  )
}

function ErrorState({ error, onRetry }: { error: unknown; onRetry: () => void }) {
  const { t } = useTranslation()
  if (error instanceof ApiError && error.status === 403) {
    return (
      <p role="alert" className="py-12 text-center">
        {t('sensors.errors.forbidden')}
      </p>
    )
  }
  return (
    <div role="alert" className="flex flex-col items-center gap-4 py-12 text-center">
      <p>{t('sensors.errors.load_failed')}</p>
      <Button variant="outline" onClick={onRetry}>
        {t('sensors.errors.retry')}
      </Button>
    </div>
  )
}
