import { useEffect, useState } from "react"
import getContacts, { getContactsTotal, type Contact } from "@/services/contacts-api"
import { getApiErrorMessage } from "@/lib/api"
import { clampPage, getPageAfterDelete } from "@/lib/pagination"
import ContactsTable, { type ContactsStatus } from "@/components/contacts/contact-table"
import TablePagination from "@/components/common/table-pagination"
import CreateContact from "@/components/contacts/contact-create"
import { ContactFormDialog } from "@/components/contacts/contact-form-dialog"
import { DeleteContactDialog } from "@/components/contacts/delete-contact-dialog"

export default function ContactsPage() {
  const [page, setPage] = useState(1)
  const [limit, setLimit] = useState(10)
  const [contacts, setContacts] = useState<Contact[]>([])
  const [total, setTotal] = useState(0)
  const [status, setStatus] = useState<ContactsStatus>("loading")
  const [error, setError] = useState("")
  const [reloadKey, setReloadKey] = useState(0)
  const [editingContact, setEditingContact] = useState<Contact | null>(null)
  const [deletingContact, setDeletingContact] = useState<Contact | null>(null)

  useEffect(() => {
    let cancelled = false

    Promise.all([getContacts(page, limit), getContactsTotal()])
      .then(([nextContacts, nextTotal]) => {
        if (cancelled) return

        const totalPages = Math.max(1, Math.ceil(nextTotal / limit))

        if (page > totalPages) {
          setPage(clampPage(page, totalPages))
          return
        }

        setContacts(nextContacts)
        setTotal(nextTotal)
        setStatus("ready")
      })
      .catch((cause) => {
        if (cancelled) return
        setError(getApiErrorMessage(cause, "Unable to load contacts."))
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
    const nextPage = getPageAfterDelete(page, contacts.length)
    setDeletingContact(null)

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
        <CreateContact onCreated={handleCreated} />
        <ContactsTable
          contacts={contacts}
          status={status}
          error={error}
          onRetry={handleRetry}
          onEdit={setEditingContact}
          onDelete={setDeletingContact}
        />
        <TablePagination
          page={page}
          limit={limit}
          total={total}
          onPageChange={changePage}
          onLimitChange={changeLimit}
        />
        {editingContact && (
          <ContactFormDialog
            open
            contact={editingContact}
            onOpenChange={(open) => {
              if (!open) setEditingContact(null)
            }}
            onSaved={() => {
              setEditingContact(null)
              handleSaved()
            }}
          />
        )}
        {deletingContact && (
          <DeleteContactDialog
            contact={deletingContact}
            onOpenChange={(open) => {
              if (!open) setDeletingContact(null)
            }}
            onDeleted={handleDeleted}
          />
        )}
      </div>
    </>
  )
}
