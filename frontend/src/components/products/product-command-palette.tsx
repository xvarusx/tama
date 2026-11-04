import { useEffect } from "react"
import {
  CommandIcon,
  PencilIcon,
  PlusIcon,
  SearchIcon,
  Trash2Icon,
} from "lucide-react"

import { type Product } from "@/services/products-api"
import {
  Command,
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
  CommandShortcut,
} from "@/components/ui/command"

interface ProductCommandPaletteProps {
  products: Product[]
  onCreate: () => void
  onEdit: (product: Product) => void
  onDelete: (product: Product) => void
  open: boolean
  onOpenChange: (open: boolean) => void
}

function searchableText(product: Product): string {
  const { attributes } = product

  return [
    attributes.name,
    attributes.brand.name,
    attributes.category?.name,
    ...attributes.variants.map((variant) => variant.sku),
  ]
    .filter(Boolean)
    .join(" ")
}

export function ProductCommandPalette({
  products,
  onCreate,
  onEdit,
  onDelete,
  open,
  onOpenChange,
}: ProductCommandPaletteProps) {
  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      if ((event.metaKey || event.ctrlKey) && event.key === "k") {
        event.preventDefault()
        onOpenChange(true)
      }
    }

    document.addEventListener("keydown", handleKeyDown)
    return () => document.removeEventListener("keydown", handleKeyDown)
  }, [onOpenChange])

  function run(action: () => void) {
    action()
    onOpenChange(false)
  }

  return (
    <CommandDialog
      open={open}
      onOpenChange={onOpenChange}
      title="Product Commands"
      description="Search products and perform actions"
    >
      <Command>
        <CommandInput placeholder="Search products, brands, or SKUs..." />
        <CommandList>
          <CommandGroup heading="Actions">
            <CommandItem value="create product new" onSelect={() => run(onCreate)}>
              <PlusIcon className="size-4" />
              <span>Create product</span>
              <CommandShortcut>
                <CommandIcon className="size-3.5" />
                <span>N</span>
              </CommandShortcut>
            </CommandItem>
          </CommandGroup>

          <CommandSeparator />

          {products.map((product) => {
            const haystack = searchableText(product)

            return (
              <CommandGroup key={product.id} heading={product.attributes.name}>
                <CommandItem
                  value={`edit ${haystack}`}
                  onSelect={() => run(() => onEdit(product))}
                >
                  <PencilIcon className="size-4" />
                  <span>Edit {product.attributes.name}</span>
                </CommandItem>
                <CommandItem
                  value={`delete ${haystack}`}
                  onSelect={() => run(() => onDelete(product))}
                >
                  <Trash2Icon className="size-4" />
                  <span>Delete {product.attributes.name}</span>
                </CommandItem>
              </CommandGroup>
            )
          })}

          <CommandEmpty>
            <SearchIcon className="mx-auto mb-2 size-4 opacity-50" />
            No products match your search.
          </CommandEmpty>
        </CommandList>
      </Command>
    </CommandDialog>
  )
}
