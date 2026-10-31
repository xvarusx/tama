import { MoreHorizontalIcon } from "lucide-react"
import { CLIENT_TYPES, type Client } from "@/services/clients-api"
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
export type ClientsStatus = "loading" | "error" | "ready"

const COLUMN_COUNT = 7

interface ClientsTableProps {
  clients: Client[]
  status: ClientsStatus
  error: string
  onRetry: () => void
  onEdit: (client: Client) => void
  onDelete: (client: Client) => void
}

export default function ClientsTable({
  clients,
  status,
  error,
  onRetry,
  onEdit,
  onDelete,
}: ClientsTableProps) {
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
              <TableHead>CIF</TableHead>
              <TableHead className="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody className="**:data-[slot=table-cell]:first:w-8">
            {status === "loading" && (
              <TableRow>
                <TableCell colSpan={COLUMN_COUNT} className="text-center text-muted-foreground">
                  Loading clients…
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

            {status === "ready" && clients.length === 0 && (
              <TableRow>
                <TableCell colSpan={COLUMN_COUNT} className="text-center text-muted-foreground">
                  No clients yet.
                </TableCell>
              </TableRow>
            )}

            {status === "ready" &&
              clients.map((client) => (
                <TableRow key={client.id}>
                  <TableCell className="font-medium">{client.name}</TableCell>
                  <TableCell>{client.email ?? "—"}</TableCell>
                  <TableCell>{client.phone ?? "—"}</TableCell>
                  <TableCell>{client.address ?? "—"}</TableCell>
                  <TableCell>
                    {CLIENT_TYPES[client.type as keyof typeof CLIENT_TYPES] ?? client.type}
                  </TableCell>
                  <TableCell>{client.cifId ?? "—"}</TableCell>
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
                        <DropdownMenuItem onClick={() => onEdit(client)}>
                          Edit
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem variant="destructive" onClick={() => onDelete(client)}>
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
