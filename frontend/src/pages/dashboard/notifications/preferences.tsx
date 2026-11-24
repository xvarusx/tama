import { useEffect, useMemo, useState } from "react"
import { Link } from "react-router-dom"
import { ArrowLeftIcon, RefreshCwIcon, SaveIcon } from "lucide-react"

import {
  getPreferences,
  savePreferences,
  type NotificationChannel,
  type PreferenceEntry,
} from "@/services/notifications-api"
import { getApiErrorMessage } from "@/lib/api"
import {
  changedPreferences,
  flattenPreferences,
} from "@/lib/notification-preferences"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
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
import { toast } from "@/components/ui/toast"

interface PreferenceRow {
  type: string
  channels: Record<NotificationChannel, boolean>
}

export default function NotificationPreferencesPage() {
  const [original, setOriginal] = useState<PreferenceEntry[]>([])
  const [entries, setEntries] = useState<PreferenceEntry[]>([])
  const [status, setStatus] = useState<"loading" | "error" | "ready">("loading")
  const [error, setError] = useState("")
  const [saving, setSaving] = useState(false)
  const [reloadKey, setReloadKey] = useState(0)

  useEffect(() => {
    let cancelled = false

    getPreferences()
      .then((matrix) => {
        if (cancelled) return
        const flattened = flattenPreferences(matrix)
        setOriginal(flattened)
        setEntries(flattened)
        setStatus("ready")
      })
      .catch((cause) => {
        if (cancelled) return
        setError(getApiErrorMessage(cause, "Unable to load preferences."))
        setStatus("error")
      })

    return () => {
      cancelled = true
    }
  }, [reloadKey])

  const rows = useMemo(() => {
    const byType = new Map<string, PreferenceRow>()
    for (const entry of entries) {
      const row = byType.get(entry.type) ?? {
        type: entry.type,
        channels: { in_app: false, email: false },
      }
      row.channels[entry.channel] = entry.enabled
      byType.set(entry.type, row)
    }
    return Array.from(byType.values())
  }, [entries])

  const hasChanges = changedPreferences(original, entries).length > 0

  function refresh() {
    setStatus("loading")
    setError("")
    setReloadKey((key) => key + 1)
  }

  function toggle(type: string, channel: NotificationChannel, enabled: boolean) {
    setEntries((current) =>
      current.map((entry) =>
        entry.type === type && entry.channel === channel
          ? { ...entry, enabled }
          : entry,
      ),
    )
  }

  async function handleSave() {
    const changed = changedPreferences(original, entries)
    if (changed.length === 0) return

    setSaving(true)
    try {
      const matrix = await savePreferences(changed)
      const refreshed = flattenPreferences(matrix)
      setOriginal(refreshed)
      setEntries(refreshed)
      toast.add({ title: "Preferences saved.", type: "success" })
    } catch (cause) {
      toast.add({
        title: "Could not save preferences",
        description: getApiErrorMessage(cause),
        type: "error",
      })
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="relative flex flex-1 flex-col gap-4 overflow-auto px-4 outline-none lg:px-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold">Notification preferences</h1>
          <p className="text-sm text-muted-foreground">
            Choose which channels deliver each notification type.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button
            size="sm"
            variant="outline"
            nativeButton={false}
            render={<Link to="/dashboard/notifications" />}
          >
            <ArrowLeftIcon className="size-4" />
            Inbox
          </Button>
          <Button
            size="sm"
            onClick={handleSave}
            disabled={!hasChanges || saving}
          >
            <SaveIcon className="size-4" />
            {saving ? "Saving…" : "Save"}
          </Button>
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
          <CardTitle>Channels by type</CardTitle>
          <CardDescription>
            Turning off both channels for a type suppresses all future
            notifications of that type. Changes apply after you press Save.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Notification type</TableHead>
                <TableHead className="w-32 text-center">In app</TableHead>
                <TableHead className="w-32 text-center">Email</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {status === "loading" && (
                <TableRow>
                  <td colSpan={3} className="text-center text-muted-foreground">
                    Loading preferences…
                  </td>
                </TableRow>
              )}
              {status === "error" && (
                <TableRow>
                  <td colSpan={3} className="text-center text-destructive">
                    {error}
                  </td>
                </TableRow>
              )}
              {status === "ready" && rows.length === 0 && (
                <TableRow>
                  <td colSpan={3} className="text-center text-muted-foreground">
                    No notification types are configured yet.
                  </td>
                </TableRow>
              )}
              {status === "ready" &&
                rows.map((row) => (
                  <TableRow key={row.type}>
                    <TableCell className="font-mono text-xs">{row.type}</TableCell>
                    <TableCell className="text-center">
                      <div className="flex justify-center">
                        <Checkbox
                          checked={row.channels.in_app}
                          onCheckedChange={(checked) =>
                            toggle(row.type, "in_app", checked)
                          }
                          aria-label={`${row.type} in-app notifications`}
                        />
                      </div>
                    </TableCell>
                    <TableCell className="text-center">
                      <div className="flex justify-center">
                        <Checkbox
                          checked={row.channels.email}
                          onCheckedChange={(checked) =>
                            toggle(row.type, "email", checked)
                          }
                          aria-label={`${row.type} email notifications`}
                        />
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  )
}
