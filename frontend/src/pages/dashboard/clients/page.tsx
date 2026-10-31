import { useEffect, useState } from "react"
import getClients, { getClientsTotal, type Client } from "@/services/clients-api"
import { getApiErrorMessage } from "@/lib/api"
import { clampPage, getPageAfterDelete } from "@/lib/pagination"
import ClientsTable, { type ClientsStatus } from "@/components/clients/client-table"
import TablePagination from "@/components/common/table-pagination"
import CreateClient from "@/components/clients/client-create"
import { ClientFormDialog } from "@/components/clients/client-form-dialog"
import { DeleteClientDialog } from "@/components/clients/delete-client-dialog"

export default function ClientsPage() {
  const [page, setPage] = useState(1)
  const [limit, setLimit] = useState(10)
  const [clients, setClients] = useState<Client[]>([])
  const [total, setTotal] = useState(0)
  const [status, setStatus] = useState<ClientsStatus>("loading")
  const [error, setError] = useState("")
  const [reloadKey, setReloadKey] = useState(0)
  const [editingClient, setEditingClient] = useState<Client | null>(null)
  const [deletingClient, setDeletingClient] = useState<Client | null>(null)

  useEffect(() => {
    let cancelled = false

    Promise.all([getClients(page, limit), getClientsTotal()])
      .then(([nextClients, nextTotal]) => {
        if (cancelled) return

        const totalPages = Math.max(1, Math.ceil(nextTotal / limit))

        if (page > totalPages) {
          setPage(clampPage(page, totalPages))
          return
        }

        setClients(nextClients)
        setTotal(nextTotal)
        setStatus("ready")
      })
      .catch((cause) => {
        if (cancelled) return
        setError(getApiErrorMessage(cause, "Unable to load clients."))
        setStatus("error")
      })

    return () => {
      cancelled = true
    }
  }, [page, limit, reloadKey])

  function beginLoading() {
    setStatus("loading")
    setError("")
  }

  function refresh() {
    beginLoading()
    setReloadKey((key) => key + 1)
  }

  function changePage(nextPage: number) {
    if (nextPage === page) return

    beginLoading()
    setPage(nextPage)
  }

  function changeLimit(nextLimit: number) {
    if (nextLimit === limit) return

    beginLoading()
    setLimit(nextLimit)
    setPage(1)
  }

  function handleCreated() {
    beginLoading()
    setPage(1)
    setReloadKey((key) => key + 1)
  }

  function handleSaved() {
    refresh()
  }

  function handleDeleted() {
    const nextPage = getPageAfterDelete(page, clients.length)
    setDeletingClient(null)

    if (nextPage !== page) {
      beginLoading()
      setPage(nextPage)
    } else {
      refresh()
    }
  }

  function handleRetry() {
    refresh()
  }

  return (
    <>
      <div className="flex-1 outline-none relative flex flex-col gap-4 overflow-auto px-4 lg:px-6">
        <CreateClient onCreated={handleCreated} />
        <ClientsTable
          clients={clients}
          status={status}
          error={error}
          onRetry={handleRetry}
          onEdit={setEditingClient}
          onDelete={setDeletingClient}
        />
        <TablePagination
          page={page}
          limit={limit}
          total={total}
          onPageChange={changePage}
          onLimitChange={changeLimit}
        />
        {editingClient && (
          <ClientFormDialog
            open
            client={editingClient}
            onOpenChange={(open) => {
              if (!open) setEditingClient(null)
            }}
            onSaved={() => {
              setEditingClient(null)
              handleSaved()
            }}
          />
        )}
        {deletingClient && (
          <DeleteClientDialog
            client={deletingClient}
            onOpenChange={(open) => {
              if (!open) setDeletingClient(null)
            }}
            onDeleted={handleDeleted}
          />
        )}
      </div>
    </>
  )
}
