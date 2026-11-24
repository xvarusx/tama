import { useEffect, useState } from "react"
import { ArrowLeftIcon, PencilIcon, Trash2Icon } from "lucide-react"
import { useNavigate, useParams } from "react-router-dom"

import {
  getContact,
  deactivateContact,
  activateContact,
  blacklistContact,
  reinstateContact,
  type Contact,
} from "@/services/contacts-api"
import { getApiErrorMessage } from "@/lib/api"
import { ContactFormDialog } from "@/components/contacts/contact-form-dialog"
import { DeleteContactDialog } from "@/components/contacts/delete-contact-dialog"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Skeleton } from "@/components/ui/skeleton"

type Status = "loading" | "error" | "ready"

function Detail({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex flex-col gap-1 border-b py-2 last:border-b-0">
      <span className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
        {label}
      </span>
      <span>{value}</span>
    </div>
  )
}

const STATUS_BADGE: Record<string, string> = {
  active: "success",
  inactive: "secondary",
  blacklisted: "destructive",
}

export default function ContactShowPage() {
  const { id } = useParams()
  const navigate = useNavigate()

  const [contact, setContact] = useState<Contact | null>(null)
  const [status, setStatus] = useState<Status>("loading")
  const [error, setError] = useState("")

  const [editOpen, setEditOpen] = useState(false)
  const [deleteOpen, setDeleteOpen] = useState(false)
  const [reloadKey, setReloadKey] = useState(0)

  useEffect(() => {
    let cancelled = false

    getContact(id)
      .then((nextContact) => {
        if (cancelled) return
        setContact(nextContact)
        setStatus("ready")
      })
      .catch((cause) => {
        if (cancelled) return
        setError(getApiErrorMessage(cause, "Unable to load the contact."))
        setStatus("error")
      })

    return () => {
      cancelled = true
    }
  }, [id, reloadKey])

  function reload() {
    setStatus("loading")
    setError("")
    setReloadKey((key) => key + 1)
  }

  async function handleStatusChange(
    action: "deactivate" | "activate" | "blacklist" | "reinstate",
  ) {
    if (!contact) return

    try {
      let updated: Contact
      if (action === "deactivate") {
        updated = await deactivateContact(contact.id)
      } else if (action === "activate") {
        updated = await activateContact(contact.id)
      } else if (action === "blacklist") {
        const reason = window.prompt(
          `Blacklist ${contact.name}? Reason:`,
        )
        if (reason === null) return
        if (!reason.trim()) return
        updated = await blacklistContact(contact.id, reason.trim())
      } else {
        updated = await reinstateContact(contact.id)
      }

      setContact(updated)
      setStatus("ready")
    } catch (cause) {
      setError(getApiErrorMessage(cause, "Unable to change contact status."))
    }
  }

  if (!id) {
    return (
      <div className="flex flex-1 flex-col items-start gap-4 px-4 lg:px-6">
        <Button variant="outline" onClick={() => navigate("/dashboard/contacts")}>
          <ArrowLeftIcon />
          Back to contacts
        </Button>
        <p className="text-destructive">Invalid contact id.</p>
      </div>
    )
  }

  if (status === "loading") {
    return (
      <div className="flex flex-1 flex-col gap-4 px-4 lg:px-6">
        <Skeleton className="h-8 w-64" />
        <div className="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
          <Skeleton className="h-80" />
          <Skeleton className="h-80" />
        </div>
      </div>
    )
  }

  if (status === "error" || !contact) {
    return (
      <div className="flex flex-1 flex-col items-start gap-4 px-4 lg:px-6">
        <Button variant="outline" onClick={() => navigate("/dashboard/contacts")}>
          <ArrowLeftIcon />
          Back to contacts
        </Button>
        <p className="text-destructive">{error}</p>
      </div>
    )
  }

  return (
    <div className="flex flex-1 flex-col gap-4 px-4 lg:px-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div className="flex flex-col gap-2">
          <Button variant="outline" size="sm" className="w-fit px-0" onClick={() => navigate("/dashboard/contacts")}>
            <ArrowLeftIcon />
            Contacts
          </Button>
          <h1 className="text-2xl font-bold">{contact.name}</h1>
          <p className="text-sm text-muted-foreground">
            {contact.contactType} · {contact.status}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" onClick={() => setEditOpen(true)}>
            <PencilIcon />
            Edit
          </Button>
          <Button variant="destructive" onClick={() => setDeleteOpen(true)}>
            <Trash2Icon />
            Delete
          </Button>
        </div>
      </div>

      {error && (
        <div className="flex flex-col gap-2 border border-destructive/40 bg-destructive/5 px-4 py-3 text-sm">
          <span className="text-destructive">{error}</span>
        </div>
      )}

      <div className="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <Card>
          <CardHeader>
            <CardTitle>Details</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-col gap-4">
            <Detail label="Name" value={contact.name} />
            <Detail label="Email" value={contact.email ?? "—"} />
            <Detail label="Phone" value={contact.phone ?? "—"} />
            <Detail label="Address" value={contact.address ?? "—"} />
            <Detail label="Tax number" value={contact.taxNumber ?? "—"} />
            <Detail label="Type" value={contact.contactType} />
            <Detail label="Status" value={contact.status} />
            <Detail
              label="Created"
              value={
                contact.createdAt
                  ? new Date(contact.createdAt).toLocaleString()
                  : "—"
              }
            />
            <Detail
              label="Updated"
              value={
                contact.updatedAt
                  ? new Date(contact.updatedAt).toLocaleString()
                  : "—"
              }
            />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Status</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-col gap-2">
            <Badge variant={STATUS_BADGE[contact.status] ?? "secondary"}>
              {contact.status}
            </Badge>
            <div className="grid gap-2">
              {contact.status === "active" && (
                <>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => handleStatusChange("deactivate")}
                  >
                    Deactivate
                  </Button>
                  <Button
                    variant="destructive"
                    size="sm"
                    onClick={() => handleStatusChange("blacklist")}
                  >
                    Blacklist
                  </Button>
                </>
              )}
              {contact.status === "inactive" && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => handleStatusChange("activate")}
                >
                  Activate
                </Button>
              )}
              {contact.status === "blacklisted" && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => handleStatusChange("reinstate")}
                >
                  Reinstate
                </Button>
              )}
            </div>
          </CardContent>
        </Card>
      </div>

      <ContactFormDialog
        open={editOpen}
        contact={contact}
        onOpenChange={(open) => {
          if (!open) setEditOpen(false)
        }}
        onSaved={(saved) => {
          setEditOpen(false)
          setContact(saved)
          reload()
        }}
      />

      {deleteOpen && (
        <DeleteContactDialog
          contact={contact}
          onOpenChange={(open) => {
            if (!open) setDeleteOpen(false)
          }}
          onDeleted={() => {
            setDeleteOpen(false)
            navigate("/dashboard/contacts")
          }}
        />
      )}
    </div>
  )
}
