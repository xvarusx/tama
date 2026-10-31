import { useState } from "react"
import { Button } from "@/components/ui/button"
import { ContactFormDialog } from "@/components/contacts/contact-form-dialog"
import { IconPlus } from "@tabler/icons-react"

interface ContactCreateProps {
  onCreated: () => void
}

export default function ContactCreate({ onCreated }: ContactCreateProps) {
  const [open, setOpen] = useState(false)

  return (
    <>
      <div className="flex items-center justify-end">
        <Button variant="outline" size="sm" onClick={() => setOpen(true)}>
          <IconPlus />
          <span className="hidden lg:inline">Add Contact</span>
        </Button>
      </div>
      {open && (
        <ContactFormDialog
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
