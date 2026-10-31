import { useState } from "react"
import { deleteClient, type Client } from "@/services/clients-api"
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

interface DeleteClientDialogProps {
  client: Client
  onOpenChange: (open: boolean) => void
  onDeleted: (client: Client) => void
}

export function DeleteClientDialog({
  client,
  onOpenChange,
  onDeleted,
}: DeleteClientDialogProps) {
  const [deleting, setDeleting] = useState(false)
  const [error, setError] = useState("")

  async function handleDelete() {
    setDeleting(true)
    setError("")

    try {
      await deleteClient(client.id)
    } catch (cause) {
      const message = getApiErrorMessage(cause, "Unable to delete client.")

      setError(message)
      toast.add({
        title: "Unable to delete client",
        description: message,
        type: "error",
      })
      return
    } finally {
      setDeleting(false)
    }

    toast.add({
      title: "Client deleted",
      description: client.name,
      type: "success",
    })
    onOpenChange(false)
    onDeleted(client)
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
          <AlertDialogTitle>Delete client</AlertDialogTitle>
          <AlertDialogDescription>
            Are you sure you want to delete {client.name}? This action cannot be
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
