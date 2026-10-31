import { useState } from "react"
import { deleteContact, type Contact } from "@/services/contacts-api"
import { getApiErrorMessage } from "@/lib/api"
import { toast } from "@/components/ui/toast"
import { FieldError } from "@/components/ui/field"
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"

interface DeleteContactDialogProps {
  contact: Contact
  onOpenChange: (open: boolean) => void
  onDeleted: (contact: Contact) => void
}

export function DeleteContactDialog({
  contact,
  onOpenChange,
  onDeleted,
}: DeleteContactDialogProps) {
  const [deleting, setDeleting] = useState(false)
  const [error, setError] = useState("")

  async function handleDelete() {
    setDeleting(true)
    setError("")

    try {
      await deleteContact(contact.id)
    } catch (cause) {
      const message = getApiErrorMessage(cause, "Unable to delete contact.")

      setError(message)
      toast.add({
        title: "Unable to delete contact",
        description: message,
        type: "error",
      })
      return
    } finally {
      setDeleting(false)
    }

    toast.add({
      title: "Contact deleted",
      description: contact.name,
      type: "success",
    })
    onOpenChange(false)
    onDeleted(contact)
  }

  return (
    <AlertDialog
      open
      onOpenChange={(nextOpen) => {
        if (!deleting) onOpenChange(nextOpen)
      }}
    >
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Delete contact</AlertDialogTitle>
          <AlertDialogDescription>
            Are you sure you want to delete {contact.name}? This action cannot be
            undone.
          </AlertDialogDescription>
        </AlertDialogHeader>
        {error && <FieldError>{error}</FieldError>}
        <AlertDialogFooter>
          <AlertDialogCancel disabled={deleting}>Cancel</AlertDialogCancel>
          <AlertDialogAction
            variant="destructive"
            disabled={deleting}
            onClick={handleDelete}
          >
            Delete
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
