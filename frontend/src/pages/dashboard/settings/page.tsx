import { useEffect, useState } from "react"
import { RefreshCwIcon } from "lucide-react"

import { getSettings, type Setting } from "@/services/payments-api"
import { getApiErrorMessage } from "@/lib/api"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
const COLUMN_COUNT = 4

export default function SettingsPage() {
  const [items, setItems] = useState<Setting[]>([])
  const [status, setStatus] = useState<"loading" | "error" | "ready">("loading")
  const [error, setError] = useState("")
  const [reloadKey, setReloadKey] = useState(0)

  useEffect(() => {
    let cancelled = false

    getSettings()
      .then((nextItems) => {
        if (cancelled) return
        setItems(nextItems)
        setStatus("ready")
      })
      .catch((cause) => {
        if (cancelled) return
        setError(getApiErrorMessage(cause, "Unable to load settings."))
        setStatus("error")
      })

    return () => {
      cancelled = true
    }
  }, [reloadKey])

  function beginLoading() {
    setStatus("loading")
    setError("")
  }

  function refresh() {
    beginLoading()
    setReloadKey((key) => key + 1)
  }

  return (
    <div className="relative flex flex-1 flex-col gap-4 overflow-auto px-4 outline-none lg:px-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold">Settings</h1>
          <p className="text-sm text-muted-foreground">
            View system configuration settings.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button size="sm" onClick={refresh}>
            <RefreshCwIcon className="size-4" />
          </Button>
        </div>
      </div>

      {status === "error" && (
        <div className="flex flex-col gap-2 border border-destructive/40 bg-destructive/5 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
          <span className="text-destructive">{error}</span>
          <Button variant="outline" size="sm" onClick={refresh}>
            Retry
          </Button>
        </div>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Settings</CardTitle>
          <CardDescription>
            {status === "ready" ? `${items.length} setting${items.length === 1 ? "" : "s"}` : "Loading settings…"}
          </CardDescription>
        </CardHeader>

        <CardContent className="flex flex-col gap-4">
          <div className="overflow-hidden border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Key</TableHead>
                  <TableHead>Value</TableHead>
                  <TableHead>Type</TableHead>
                  <TableHead className="text-right">Description</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {status === "loading" && (
                  <TableRow>
                    <td colSpan={COLUMN_COUNT} className="text-center text-muted-foreground">
                      Loading settings…
                    </td>
                  </TableRow>
                )}
                {status === "error" && (
                  <TableRow>
                    <td colSpan={COLUMN_COUNT} className="text-center">
                      <span className="text-destructive">{error}</span>{" "}
                      <Button variant="outline" size="sm" onClick={refresh}>
                        Retry
                      </Button>
                    </td>
                  </TableRow>
                )}
                {status === "ready" && items.length === 0 && (
                  <TableRow>
                    <td colSpan={COLUMN_COUNT} className="text-center text-muted-foreground">
                      No settings yet.
                    </td>
                  </TableRow>
                )}
                {status === "ready" &&
                  items.map((setting) => (
                    <TableRow key={setting.key}>
                      <TableCell className="font-mono text-xs">
                        {setting.key}
                      </TableCell>
                      <TableCell>{setting.attributes.value}</TableCell>
                      <TableCell className="text-muted-foreground">
                        {setting.attributes.type}
                      </TableCell>
                      <TableCell className="text-right">
                        {setting.attributes.description ?? "—"}
                      </TableCell>
                    </TableRow>
                  ))}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
