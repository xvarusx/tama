import { useState, type FormEvent } from "react"
import {
  createClient,
  updateClient,
  CLIENT_TYPES,
  type Client,
} from "@/services/clients-api"
import { getApiErrorMessage } from "@/lib/api"
import {
  createEmptyClientForm,
  toClientFormValues,
  toClientInput,
  validateClientForm,
  type ClientFormErrors,
  type ClientFormValues,
} from "@/lib/client-validation"
import { toast } from "@/components/ui/toast"
import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Field, FieldError, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Textarea } from "@/components/ui/textarea"

interface ClientFormDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  client?: Client | null
  onSaved: (client: Client, mode: "create" | "edit") => void
}

export function ClientFormDialog({
  open,
  onOpenChange,
  client,
  onSaved,
}: ClientFormDialogProps) {
  const [values, setValues] = useState<ClientFormValues>(() =>
    client ? toClientFormValues(client) : createEmptyClientForm(),
  )
  const [errors, setErrors] = useState<ClientFormErrors>({})
  const [formError, setFormError] = useState("")
  const [saving, setSaving] = useState(false)

  const mode = client ? "edit" : "create"

  function setField<K extends keyof ClientFormValues>(
    field: K,
    value: ClientFormValues[K],
  ) {
    setValues((current) => ({ ...current, [field]: value }))
    setErrors((current) => {
      if (!current[field]) return current

      const next = { ...current }
      delete next[field]
      return next
    })
    setFormError("")
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError("")

    const validationErrors = validateClientForm(values)

    if (Object.keys(validationErrors).length > 0) {
      setErrors(validationErrors)
      return
    }

    setErrors({})
    setSaving(true)

    let saved: Client
    try {
      const input = toClientInput(values)
      saved = client
        ? await updateClient(client.id, input)
        : await createClient(input)
    } catch (cause) {
      const message = getApiErrorMessage(cause, "Unable to save client.")

      setFormError(message)
      toast.add({
        title: "Unable to save client",
        description: message,
        type: "error",
      })
      return
    } finally {
      setSaving(false)
    }

    toast.add({
      title: mode === "edit" ? "Client updated" : "Client created",
      description: saved.name,
      type: "success",
    })
    onOpenChange(false)
    onSaved(saved, mode)
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(nextOpen) => {
        if (!saving) onOpenChange(nextOpen)
      }}
    >
      <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{mode === "edit" ? "Edit client" : "Add client"}</DialogTitle>
          <DialogDescription>
            {mode === "edit"
              ? `Update the details for ${client?.name}.`
              : "Add a new client to your directory."}
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-6">
          <div className="grid gap-4 sm:grid-cols-2">
            <Field className="sm:col-span-2" data-invalid={!!errors.name}>
              <FieldLabel htmlFor="client-name">Name</FieldLabel>
              <Input
                id="client-name"
                value={values.name}
                onChange={(event) => setField("name", event.target.value)}
                aria-invalid={errors.name ? true : undefined}
                aria-describedby={errors.name ? "client-name-error" : undefined}
              />
              {errors.name && <FieldError id="client-name-error">{errors.name}</FieldError>}
            </Field>

            <Field data-invalid={!!errors.type}>
              <FieldLabel htmlFor="client-type">Type</FieldLabel>
              <Select
                items={CLIENT_TYPES}
                value={values.type}
                onValueChange={(value) => setField("type", value ?? "")}
              >
                <SelectTrigger
                  id="client-type"
                  className="w-full"
                  aria-invalid={errors.type ? true : undefined}
                  aria-describedby={errors.type ? "client-type-error" : undefined}
                >
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(CLIENT_TYPES).map(([value, label]) => (
                    <SelectItem key={value} value={value}>
                      {label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.type && <FieldError id="client-type-error">{errors.type}</FieldError>}
            </Field>

            <Field data-invalid={!!errors.email}>
              <FieldLabel htmlFor="client-email">Email</FieldLabel>
              <Input
                id="client-email"
                type="email"
                value={values.email}
                onChange={(event) => setField("email", event.target.value)}
                aria-invalid={errors.email ? true : undefined}
                aria-describedby={errors.email ? "client-email-error" : undefined}
              />
              {errors.email && <FieldError id="client-email-error">{errors.email}</FieldError>}
            </Field>

            <Field data-invalid={!!errors.phone}>
              <FieldLabel htmlFor="client-phone">Phone</FieldLabel>
              <Input
                id="client-phone"
                type="tel"
                value={values.phone}
                onChange={(event) => setField("phone", event.target.value)}
                aria-invalid={errors.phone ? true : undefined}
                aria-describedby={errors.phone ? "client-phone-error" : undefined}
              />
              {errors.phone && <FieldError id="client-phone-error">{errors.phone}</FieldError>}
            </Field>

            <Field data-invalid={!!errors.cifId}>
              <FieldLabel htmlFor="client-cif">CIF</FieldLabel>
              <Input
                id="client-cif"
                value={values.cifId}
                onChange={(event) => setField("cifId", event.target.value)}
                aria-invalid={errors.cifId ? true : undefined}
                aria-describedby={errors.cifId ? "client-cif-error" : undefined}
              />
              {errors.cifId && <FieldError id="client-cif-error">{errors.cifId}</FieldError>}
            </Field>

            <Field className="sm:col-span-2" data-invalid={!!errors.address}>
              <FieldLabel htmlFor="client-address">Address</FieldLabel>
              <Textarea
                id="client-address"
                value={values.address}
                onChange={(event) => setField("address", event.target.value)}
                aria-invalid={errors.address ? true : undefined}
                aria-describedby={errors.address ? "client-address-error" : undefined}
              />
              {errors.address && <FieldError id="client-address-error">{errors.address}</FieldError>}
            </Field>
          </div>

          {formError && <FieldError id="client-form-error">{formError}</FieldError>}

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
              disabled={saving}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={saving}>
              {mode === "edit" ? "Save changes" : "Create client"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
