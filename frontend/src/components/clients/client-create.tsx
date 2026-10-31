import { useState } from "react"
import { Button } from "@/components/ui/button"
import { ClientFormDialog } from "@/components/clients/client-form-dialog"
import { IconPlus } from "@tabler/icons-react"

interface ClientCreateProps {
  onCreated: () => void
}

export default function ClientCreate({ onCreated }: ClientCreateProps) {
  const [open, setOpen] = useState(false)

  return (
    <>
      <div className="flex items-center justify-end">
        <Button variant="outline" size="sm" onClick={() => setOpen(true)}>
          <IconPlus />
          <span className="hidden lg:inline">Add Client</span>
        </Button>
      </div>
      {open && (
        <ClientFormDialog
          open
          onOpenChange={setOpen}
          onSaved={() => {
            setOpen(false)
            onCreated()
          }}
        />
      )}
    </>
  )
}
