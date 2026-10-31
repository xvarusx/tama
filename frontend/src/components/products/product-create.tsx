import { useState } from "react"

import { Button } from "@/components/ui/button"
import { ProductFormDialog } from "@/components/products/product-form-dialog"
import { IconPlus } from "@tabler/icons-react"

interface ProductCreateProps {
  onCreated: () => void
}

export default function ProductCreate({ onCreated }: ProductCreateProps) {
  const [open, setOpen] = useState(false)

  return (
    <>
      <div className="flex items-center justify-end">
        <Button variant="outline" size="sm" onClick={() => setOpen(true)}>
          <IconPlus />
          <span className="hidden lg:inline">Add Product</span>
        </Button>
      </div>
      {open && (
        <ProductFormDialog
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
