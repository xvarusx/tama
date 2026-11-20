import { useEffect, useState } from "react"
import { Link } from "react-router-dom"
import axios from "axios"
import {
  ArrowLeftIcon,
  PencilIcon,
  PlusIcon,
  RefreshCwIcon,
  RotateCcwIcon,
  SearchIcon,
  Trash2Icon,
} from "lucide-react"

import {
  createTemplate,
  deleteTemplate,
  formatRelativeTime,
  getDeliveries,
  getDelivery,
  getSettings,
  getTemplates,
  requeueDelivery,
  updateSetting,
  updateTemplate,
  type Delivery,
  type DeliveryAttempt,
  type DeliveryStatus,
  type NotificationChannel,
  type NotificationPagination,
  type Setting,
  type Template,
  type TemplateInput,
} from "@/services/notifications-api"
import { getApiErrorMessage } from "@/lib/api"
import {
  buildDeliveryQuery,
  canRequeue,
  deliveryStatusLabel,
  validateSettingValue,
  validateTemplate,
  type TemplateFormErrors,
} from "@/lib/notification-admin"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Field, FieldLabel } from "@/components/ui/field"
import {
  InputGroup,
  InputGroupAddon,
  InputGroupInput,
} from "@/components/ui/input-group"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Textarea } from "@/components/ui/textarea"
import { toast } from "@/components/ui/toast"
import TablePagination from "@/components/common/table-pagination"

const DELIVERY_STATUSES: DeliveryStatus[] = [
  "queued",
  "sending",
  "sent",
  "retry_waiting",
  "failed",
]
const CHANNELS: NotificationChannel[] = ["in_app", "email"]

function isForbidden(error: unknown): boolean {
  return axios.isAxiosError(error) && error.response?.status === 403
}

type ReportError = (error: unknown, fallback: string) => string

const EMPTY_TEMPLATE: TemplateInput = {
  type: "",
  channel: "email",
  locale: "en",
  subject: "",
  bodyTemplate: "",
}

function DeliveriesTab({ reportError }: { reportError: ReportError }) {
  const [statusFilter, setStatusFilter] = useState<DeliveryStatus | "all">("all")
  const [channelFilter, setChannelFilter] = useState<NotificationChannel | "all">(
    "all",
  )
  const [searchInput, setSearchInput] = useState("")
  const [search, setSearch] = useState("")
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)

  const [items, setItems] = useState<Delivery[]>([])
  const [pagination, setPagination] = useState<NotificationPagination | null>(null)
  const [loadStatus, setLoadStatus] = useState<"loading" | "error" | "ready">(
    "loading",
  )
  const [error, setError] = useState("")
  const [reloadKey, setReloadKey] = useState(0)
  const [detail, setDetail] = useState<Delivery | null>(null)
  const [detailLoading, setDetailLoading] = useState(false)
  const [busyId, setBusyId] = useState<string | null>(null)

  useEffect(() => {
    const nextSearch = searchInput.trim()
    if (nextSearch === search) return
    const timer = window.setTimeout(() => {
      setLoadStatus("loading")
      setSearch(nextSearch)
      setPage(1)
    }, 300)
    return () => window.clearTimeout(timer)
  }, [searchInput, search])

  useEffect(() => {
    let cancelled = false

    getDeliveries(
      buildDeliveryQuery({
        status: statusFilter,
        channel: channelFilter,
        search,
        page,
        pageSize,
      }),
    )
      .then((result) => {
        if (cancelled) return
        setItems(result.items)
        setPagination(result.pagination)
        setLoadStatus("ready")
      })
      .catch((cause) => {
        if (cancelled) return
        setError(reportError(cause, "Unable to load deliveries."))
        setLoadStatus("error")
      })

    return () => {
      cancelled = true
    }
  }, [statusFilter, channelFilter, search, page, pageSize, reloadKey, reportError])

  function refresh() {
    setLoadStatus("loading")
    setError("")
    setReloadKey((key) => key + 1)
  }

  async function openDetail(id: string) {
    setDetailLoading(true)
    try {
      setDetail(await getDelivery(id))
    } catch (cause) {
      toast.add({
        title: "Could not load delivery",
        description: getApiErrorMessage(cause),
        type: "error",
      })
    } finally {
      setDetailLoading(false)
    }
  }

  async function handleRequeue(id: string) {
    setBusyId(id)
    try {
      await requeueDelivery(id)
      toast.add({ title: "Delivery requeued.", type: "success" })
      refresh()
    } catch (cause) {
      toast.add({
        title: "Could not requeue delivery",
        description: getApiErrorMessage(cause),
        type: "error",
      })
    } finally {
      setBusyId(null)
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <Card>
        <CardHeader>
          <CardTitle>Deliveries</CardTitle>
          <CardDescription>
            Inspect email deliveries, attempts and failures.
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <InputGroup className="sm:max-w-xs">
              <InputGroupAddon>
                <SearchIcon />
              </InputGroupAddon>
              <InputGroupInput
                placeholder="Search recipient or type..."
                value={searchInput}
                onChange={(event) => setSearchInput(event.target.value)}
                aria-label="Search deliveries"
              />
            </InputGroup>
            <div className="flex flex-wrap items-center gap-2">
              <Select
                value={statusFilter}
                onValueChange={(value) => {
                  setStatusFilter((value as DeliveryStatus | "all") ?? "all")
                  setPage(1)
                  setLoadStatus("loading")
                }}
              >
                <SelectTrigger className="w-40" aria-label="Filter by status">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectGroup>
                    <SelectItem value="all">All statuses</SelectItem>
                    {DELIVERY_STATUSES.map((status) => (
                      <SelectItem key={status} value={status}>
                        {deliveryStatusLabel(status)}
                      </SelectItem>
                    ))}
                  </SelectGroup>
                </SelectContent>
              </Select>
              <Select
                value={channelFilter}
                onValueChange={(value) => {
                  setChannelFilter((value as NotificationChannel | "all") ?? "all")
                  setPage(1)
                  setLoadStatus("loading")
                }}
              >
                <SelectTrigger className="w-36" aria-label="Filter by channel">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectGroup>
                    <SelectItem value="all">All channels</SelectItem>
                    {CHANNELS.map((channel) => (
                      <SelectItem key={channel} value={channel}>
                        {channel === "in_app" ? "In app" : "Email"}
                      </SelectItem>
                    ))}
                  </SelectGroup>
                </SelectContent>
              </Select>
              <Button size="sm" onClick={refresh}>
                <RefreshCwIcon className="size-4" />
              </Button>
            </div>
          </div>

          {loadStatus === "error" && (
            <div className="border border-destructive/40 bg-destructive/5 px-4 py-3 text-sm text-destructive">
              {error}
            </div>
          )}

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Recipient</TableHead>
                <TableHead>Type</TableHead>
                <TableHead>Channel</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="text-right">Attempts</TableHead>
                <TableHead>Last error</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {loadStatus === "loading" && (
                <TableRow>
                  <td colSpan={7} className="text-center text-muted-foreground">
                    Loading deliveries…
                  </td>
                </TableRow>
              )}
              {loadStatus === "ready" && items.length === 0 && (
                <TableRow>
                  <td colSpan={7} className="text-center text-muted-foreground">
                    No deliveries found.
                  </td>
                </TableRow>
              )}
              {loadStatus === "ready" &&
                items.map((delivery) => (
                  <TableRow key={delivery.id}>
                    <TableCell>{delivery.recipientEmail}</TableCell>
                    <TableCell className="text-xs text-muted-foreground">
                      {delivery.notificationType}
                    </TableCell>
                    <TableCell>{delivery.channel}</TableCell>
                    <TableCell>
                      <Badge
                        variant={
                          delivery.status === "failed"
                            ? "destructive"
                            : delivery.status === "sent"
                              ? "secondary"
                              : "default"
                        }
                      >
                        {deliveryStatusLabel(delivery.status)}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right tabular-nums">
                      {delivery.attemptCount}
                    </TableCell>
                    <TableCell className="max-w-48 truncate text-xs text-muted-foreground">
                      {delivery.lastError ?? "—"}
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex justify-end gap-1">
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => openDetail(delivery.id)}
                        >
                          View
                        </Button>
                        {canRequeue(delivery.status) && (
                          <Button
                            variant="ghost"
                            size="sm"
                            disabled={busyId === delivery.id}
                            onClick={() => handleRequeue(delivery.id)}
                          >
                            <RotateCcwIcon className="size-4" />
                            Requeue
                          </Button>
                        )}
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
            </TableBody>
          </Table>
        </CardContent>
        <TablePagination
          page={page}
          limit={pageSize}
          total={pagination?.totalItems ?? 0}
          onPageChange={(nextPage) => {
            setLoadStatus("loading")
            setPage(nextPage)
          }}
          onLimitChange={(limit) => {
            setLoadStatus("loading")
            setPageSize(limit)
            setPage(1)
          }}
        />
      </Card>

      <Dialog open={detail !== null} onOpenChange={(open) => !open && setDetail(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Delivery detail</DialogTitle>
            <DialogDescription>
              {detailLoading
                ? "Loading…"
                : detail
                  ? `${detail.recipientEmail} · ${detail.notificationType}`
                  : ""}
            </DialogDescription>
          </DialogHeader>
          {detail && (
            <div className="flex flex-col gap-4">
              <div className="grid grid-cols-2 gap-2 text-sm">
                <span className="text-muted-foreground">Status</span>
                <span>{deliveryStatusLabel(detail.status)}</span>
                <span className="text-muted-foreground">Channel</span>
                <span>{detail.channel}</span>
                <span className="text-muted-foreground">Subject</span>
                <span>{detail.subject}</span>
                <span className="text-muted-foreground">Next attempt</span>
                <span>
                  {detail.nextAttemptAt
                    ? formatRelativeTime(detail.nextAttemptAt)
                    : "—"}
                </span>
              </div>
              <div>
                <p className="mb-2 text-sm font-semibold">Attempts</p>
                {detail.attempts && detail.attempts.length > 0 ? (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>#</TableHead>
                        <TableHead>At</TableHead>
                        <TableHead>Error</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {detail.attempts.map((attempt: DeliveryAttempt) => (
                        <TableRow key={attempt.attemptNumber}>
                          <TableCell>{attempt.attemptNumber}</TableCell>
                          <TableCell>
                            {formatRelativeTime(attempt.attemptedAt)}
                          </TableCell>
                          <TableCell className="text-xs text-muted-foreground">
                            {attempt.error ?? attempt.providerMessageId ?? "—"}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                ) : (
                  <p className="text-sm text-muted-foreground">No attempts yet.</p>
                )}
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}

function TemplatesTab({ reportError }: { reportError: ReportError }) {
  const [templates, setTemplates] = useState<Template[]>([])
  const [loadStatus, setLoadStatus] = useState<"loading" | "error" | "ready">(
    "loading",
  )
  const [error, setError] = useState("")
  const [reloadKey, setReloadKey] = useState(0)
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<Template | null>(null)
  const [draft, setDraft] = useState<TemplateInput>(EMPTY_TEMPLATE)
  const [formErrors, setFormErrors] = useState<TemplateFormErrors>({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    let cancelled = false

    getTemplates()
      .then((result) => {
        if (cancelled) return
        setTemplates(result)
        setLoadStatus("ready")
      })
      .catch((cause) => {
        if (cancelled) return
        setError(reportError(cause, "Unable to load templates."))
        setLoadStatus("error")
      })

    return () => {
      cancelled = true
    }
  }, [reloadKey, reportError])

  function refresh() {
    setLoadStatus("loading")
    setError("")
    setReloadKey((key) => key + 1)
  }

  function openCreate() {
    setEditing(null)
    setDraft(EMPTY_TEMPLATE)
    setFormErrors({})
    setFormOpen(true)
  }

  function openEdit(template: Template) {
    setEditing(template)
    setDraft({
      type: template.type,
      channel: template.channel,
      locale: template.locale,
      subject: template.subject,
      bodyTemplate: template.bodyTemplate,
    })
    setFormErrors({})
    setFormOpen(true)
  }

  async function handleSave() {
    const errors = validateTemplate(draft)
    setFormErrors(errors)
    if (Object.keys(errors).length > 0) return

    setSaving(true)
    try {
      if (editing) {
        await updateTemplate(editing.id, draft)
      } else {
        await createTemplate(draft)
      }
      toast.add({ title: "Template saved.", type: "success" })
      setFormOpen(false)
      refresh()
    } catch (cause) {
      toast.add({
        title: "Could not save template",
        description: getApiErrorMessage(cause),
        type: "error",
      })
    } finally {
      setSaving(false)
    }
  }

  async function handleDelete(template: Template) {
    try {
      await deleteTemplate(template.id)
      toast.add({ title: "Template deleted.", type: "success" })
      refresh()
    } catch (cause) {
      toast.add({
        title: "Could not delete template",
        description: getApiErrorMessage(cause),
        type: "error",
      })
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <div>
            <CardTitle>Templates</CardTitle>
            <CardDescription>
              Edit the subject and body used for each type, channel and locale.
            </CardDescription>
          </div>
          <Button size="sm" onClick={openCreate}>
            <PlusIcon className="size-4" />
            New template
          </Button>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          {loadStatus === "error" && (
            <div className="border border-destructive/40 bg-destructive/5 px-4 py-3 text-sm text-destructive">
              {error}
            </div>
          )}
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Type</TableHead>
                <TableHead>Channel</TableHead>
                <TableHead>Locale</TableHead>
                <TableHead>Subject</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {loadStatus === "loading" && (
                <TableRow>
                  <td colSpan={5} className="text-center text-muted-foreground">
                    Loading templates…
                  </td>
                </TableRow>
              )}
              {loadStatus === "ready" && templates.length === 0 && (
                <TableRow>
                  <td colSpan={5} className="text-center text-muted-foreground">
                    No templates yet.
                  </td>
                </TableRow>
              )}
              {loadStatus === "ready" &&
                templates.map((template) => (
                  <TableRow key={template.id}>
                    <TableCell className="text-xs">{template.type}</TableCell>
                    <TableCell>{template.channel}</TableCell>
                    <TableCell>{template.locale}</TableCell>
                    <TableCell className="max-w-64 truncate">
                      {template.subject}
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex justify-end gap-1">
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => openEdit(template)}
                        >
                          <PencilIcon className="size-4" />
                          Edit
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => handleDelete(template)}
                        >
                          <Trash2Icon className="size-4" />
                          Delete
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {editing ? "Edit template" : "Create template"}
            </DialogTitle>
            <DialogDescription>
              Placeholders such as {"{{ invoiceNumber }}"} are replaced at send
              time.
            </DialogDescription>
          </DialogHeader>
          <div className="flex flex-col gap-3">
            <Field>
              <FieldLabel htmlFor="template-type">Type</FieldLabel>
              <Input
                id="template-type"
                value={draft.type}
                onChange={(event) =>
                  setDraft({ ...draft, type: event.target.value })
                }
              />
              {formErrors.type && (
                <span className="text-xs text-destructive">{formErrors.type}</span>
              )}
            </Field>
            <Field>
              <FieldLabel htmlFor="template-channel">Channel</FieldLabel>
              <Select
                value={draft.channel}
                onValueChange={(value) =>
                  setDraft({
                    ...draft,
                    channel: (value as NotificationChannel) ?? "email",
                  })
                }
              >
                <SelectTrigger id="template-channel">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectGroup>
                    {CHANNELS.map((channel) => (
                      <SelectItem key={channel} value={channel}>
                        {channel === "in_app" ? "In app" : "Email"}
                      </SelectItem>
                    ))}
                  </SelectGroup>
                </SelectContent>
              </Select>
            </Field>
            <Field>
              <FieldLabel htmlFor="template-locale">Locale</FieldLabel>
              <Input
                id="template-locale"
                value={draft.locale}
                onChange={(event) =>
                  setDraft({ ...draft, locale: event.target.value })
                }
              />
              {formErrors.locale && (
                <span className="text-xs text-destructive">{formErrors.locale}</span>
              )}
            </Field>
            <Field>
              <FieldLabel htmlFor="template-subject">Subject</FieldLabel>
              <Input
                id="template-subject"
                value={draft.subject}
                onChange={(event) =>
                  setDraft({ ...draft, subject: event.target.value })
                }
              />
              {formErrors.subject && (
                <span className="text-xs text-destructive">{formErrors.subject}</span>
              )}
            </Field>
            <Field>
              <FieldLabel htmlFor="template-body">Body</FieldLabel>
              <Textarea
                id="template-body"
                rows={6}
                value={draft.bodyTemplate}
                onChange={(event) =>
                  setDraft({ ...draft, bodyTemplate: event.target.value })
                }
              />
              {formErrors.bodyTemplate && (
                <span className="text-xs text-destructive">
                  {formErrors.bodyTemplate}
                </span>
              )}
            </Field>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setFormOpen(false)}>
              Cancel
            </Button>
            <Button onClick={handleSave} disabled={saving}>
              {saving ? "Saving…" : "Save"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}

function SettingsTab({ reportError }: { reportError: ReportError }) {
  const [settings, setSettings] = useState<Setting[]>([])
  const [values, setValues] = useState<Record<string, string>>({})
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [loadStatus, setLoadStatus] = useState<"loading" | "error" | "ready">(
    "loading",
  )
  const [error, setError] = useState("")
  const [reloadKey, setReloadKey] = useState(0)
  const [savingKey, setSavingKey] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false

    getSettings()
      .then((result) => {
        if (cancelled) return
        setSettings(result)
        setValues(
          Object.fromEntries(result.map((setting) => [setting.key, setting.value])),
        )
        setLoadStatus("ready")
      })
      .catch((cause) => {
        if (cancelled) return
        setError(reportError(cause, "Unable to load settings."))
        setLoadStatus("error")
      })

    return () => {
      cancelled = true
    }
  }, [reloadKey, reportError])

  function refresh() {
    setLoadStatus("loading")
    setError("")
    setReloadKey((key) => key + 1)
  }

  async function handleSave(setting: Setting) {
    const value = values[setting.key] ?? ""
    const validationError = validateSettingValue(setting.key, value)
    if (validationError) {
      setErrors((current) => ({ ...current, [setting.key]: validationError }))
      return
    }

    setSavingKey(setting.key)
    try {
      await updateSetting(setting.key, value)
      toast.add({ title: "Setting updated.", type: "success" })
      setErrors((current) => ({ ...current, [setting.key]: "" }))
      refresh()
    } catch (cause) {
      toast.add({
        title: "Could not update setting",
        description: getApiErrorMessage(cause),
        type: "error",
      })
    } finally {
      setSavingKey(null)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Settings</CardTitle>
        <CardDescription>Update notification settings by key.</CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        {loadStatus === "error" && (
          <div className="border border-destructive/40 bg-destructive/5 px-4 py-3 text-sm text-destructive">
            {error}
          </div>
        )}
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Key</TableHead>
              <TableHead>Value</TableHead>
              <TableHead>Description</TableHead>
              <TableHead className="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {loadStatus === "loading" && (
              <TableRow>
                <td colSpan={4} className="text-center text-muted-foreground">
                  Loading settings…
                </td>
              </TableRow>
            )}
            {loadStatus === "ready" && settings.length === 0 && (
              <TableRow>
                <td colSpan={4} className="text-center text-muted-foreground">
                  No settings yet.
                </td>
              </TableRow>
            )}
            {loadStatus === "ready" &&
              settings.map((setting) => (
                <TableRow key={setting.key}>
                  <TableCell className="font-mono text-xs">{setting.key}</TableCell>
                  <TableCell>
                    <div className="flex flex-col gap-1">
                      <Input
                        className="w-36"
                        value={values[setting.key] ?? ""}
                        onChange={(event) =>
                          setValues({
                            ...values,
                            [setting.key]: event.target.value,
                          })
                        }
                        aria-label={`${setting.key} value`}
                      />
                      {errors[setting.key] && (
                        <span className="text-xs text-destructive">
                          {errors[setting.key]}
                        </span>
                      )}
                    </div>
                  </TableCell>
                  <TableCell className="text-xs text-muted-foreground">
                    {setting.description ?? "—"}
                  </TableCell>
                  <TableCell className="text-right">
                    <Button
                      size="sm"
                      disabled={savingKey === setting.key}
                      onClick={() => handleSave(setting)}
                    >
                      Save
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
          </TableBody>
        </Table>
      </CardContent>
    </Card>
  )
}

export default function NotificationAdminPage() {
  const [forbidden, setForbidden] = useState(false)

  function reportError(error: unknown, fallback: string): string {
    if (isForbidden(error)) setForbidden(true)
    return getApiErrorMessage(error, fallback)
  }

  if (forbidden) {
    return (
      <div className="relative flex flex-1 flex-col gap-4 px-4 outline-none lg:px-6">
        <h1 className="text-2xl font-bold">Notification admin</h1>
        <div className="border border-destructive/40 bg-destructive/5 px-4 py-6 text-center">
          <p className="font-medium text-destructive">
            You do not have permission to view this page.
          </p>
          <p className="mt-1 text-sm text-muted-foreground">
            Admin access requires the ROLE_ADMIN role.
          </p>
          <Button
            variant="outline"
            size="sm"
            className="mt-4"
            nativeButton={false}
            render={<Link to="/dashboard/notifications" />}
          >
            <ArrowLeftIcon className="size-4" />
            Back to inbox
          </Button>
        </div>
      </div>
    )
  }

  return (
    <div className="relative flex flex-1 flex-col gap-4 overflow-auto px-4 outline-none lg:px-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold">Notification admin</h1>
          <p className="text-sm text-muted-foreground">
            Manage deliveries, templates and notification settings.
          </p>
        </div>
        <Button
          size="sm"
          variant="outline"
          nativeButton={false}
          render={<Link to="/dashboard/notifications" />}
        >
          <ArrowLeftIcon className="size-4" />
          Inbox
        </Button>
      </div>

      <Tabs defaultValue="deliveries">
        <TabsList>
          <TabsTrigger value="deliveries">Deliveries</TabsTrigger>
          <TabsTrigger value="templates">Templates</TabsTrigger>
          <TabsTrigger value="settings">Settings</TabsTrigger>
        </TabsList>
        <TabsContent value="deliveries">
          <DeliveriesTab reportError={reportError} />
        </TabsContent>
        <TabsContent value="templates">
          <TemplatesTab reportError={reportError} />
        </TabsContent>
        <TabsContent value="settings">
          <SettingsTab reportError={reportError} />
        </TabsContent>
      </Tabs>
    </div>
  )
}
