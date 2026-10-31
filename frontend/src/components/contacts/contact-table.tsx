import { MoreHorizontalIcon } from "lucide-react"
import { CONTACT_TYPES, type Contact } from "@/services/contacts-api"
import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
export type ContactsStatus = "loading" | "error" | "ready"

const COLUMN_COUNT = 7

interface ContactsTableProps {
  contacts: Contact[]
  status: ContactsStatus
  error: string
  onRetry: () => void
  onEdit: (contact: Contact) => void
  onDelete: (contact: Contact) => void
}

export default function ContactsTable({
  contacts,
  status,
  error,
  onRetry,
  onEdit,
  onDelete,
}: ContactsTableProps) {
  return (
    <>
      <div className="overflow-hidden border">
        <Table>
          <TableHeader className="sticky top-0 z-10 bg-muted">
            <TableRow>
              <TableHead>Full Name</TableHead>
              <TableHead>Email</TableHead>
              <TableHead>Phone</TableHead>
              <TableHead>Address</TableHead>
              <TableHead>Type</TableHead>
              <TableHead>Tax number</TableHead>
              <TableHead className="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody className="**:data-[slot=table-cell]:first:w-8">
            {status === "loading" && (
              <TableRow>
                <TableCell colSpan={COLUMN_COUNT} className="text-center text-muted-foreground">
                  Loading contacts…
                </TableCell>
              </TableRow>
            )}

            {status === "error" && (
              <TableRow>
                <TableCell colSpan={COLUMN_COUNT} className="text-center">
                  <span className="text-destructive">{error}</span>
                  <Button variant="outline" size="sm" className="ml-3" onClick={onRetry}>
                    Retry
                  </Button>
                </TableCell>
              </TableRow>
            )}

            {status === "ready" && contacts.length === 0 && (
              <TableRow>
                <TableCell colSpan={COLUMN_COUNT} className="text-center text-muted-foreground">
                  No contacts yet.
                </TableCell>
              </TableRow>
            )}

            {status === "ready" &&
              contacts.map((contact) => (
                <TableRow key={contact.id}>
                  <TableCell className="font-medium">{contact.name}</TableCell>
                  <TableCell>{contact.email ?? "—"}</TableCell>
                  <TableCell>{contact.phone ?? "—"}</TableCell>
                  <TableCell>{contact.address ?? "—"}</TableCell>
                  <TableCell>
                    {CONTACT_TYPES[contact.contactType] ?? contact.contactType}
                  </TableCell>
                  <TableCell>{contact.taxNumber ?? "—"}</TableCell>
                  <TableCell className="text-right">
                    <DropdownMenu>
                      <DropdownMenuTrigger
                        render={
                          <Button variant="ghost" size="icon" className="size-8">
                            <MoreHorizontalIcon />
                            <span className="sr-only">Open menu</span>
                          </Button>
                        }
                      />
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem onClick={() => onEdit(contact)}>
                          Edit
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem variant="destructive" onClick={() => onDelete(contact)}>
                          Delete
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </TableCell>
                </TableRow>
              ))}
          </TableBody>
        </Table>
      </div>
    </>
  )
}
