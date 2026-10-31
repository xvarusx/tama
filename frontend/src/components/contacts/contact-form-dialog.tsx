import { useState, type FormEvent } from "react"
import {
  createContact,
  updateContact,
  CONTACT_TYPES,
  type Contact,
  type ContactType,
} from "@/services/contacts-api"
import { getApiErrorMessage } from "@/lib/api"
import {
  createEmptyContactForm,
  toContactFormValues,
  toContactInput,
  toContactUpdateInput,
  validateContactForm,
  type ContactFormErrors,
  type ContactFormValues,
} from "@/lib/contact-validation"
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

interface ContactFormDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  contact?: Contact | null
  onSaved: (contact: Contact, mode: "create" | "edit") => void
}

export function ContactFormDialog({
  open,
  onOpenChange,
  contact,
  onSaved,
}: ContactFormDialogProps) {
  const [values, setValues] = useState<ContactFormValues>(() =>
    contact ? toContactFormValues(contact) : createEmptyContactForm(),
  )
  const [errors, setErrors] = useState<ContactFormErrors>({})
  const [formError, setFormError] = useState("")
  const [saving, setSaving] = useState(false)

  const mode = contact ? "edit" : "create"

  function setField<K extends keyof ContactFormValues>(
    field: K,
    value: ContactFormValues[K],
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

    const validationErrors = validateContactForm(values)

    if (Object.keys(validationErrors).length > 0) {
      setErrors(validationErrors)
      return
    }

    setErrors({})
    setSaving(true)

    let saved: Contact
    try {
      saved = contact
        ? await updateContact(contact.id, toContactUpdateInput(values))
        : await createContact(toContactInput(values))
    } catch (cause) {
      const message = getApiErrorMessage(cause, "Unable to save contact.")

      setFormError(message)
      toast.add({
        title: "Unable to save contact",
        description: message,
        type: "error",
      })
      return
    } finally {
      setSaving(false)
    }

    toast.add({
      title: mode === "edit" ? "Contact updated" : "Contact created",
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
          <DialogTitle>{mode === "edit" ? "Edit contact" : "Add contact"}</DialogTitle>
          <DialogDescription>
            {mode === "edit"
              ? `Update the details for ${contact?.name}.`
              : "Add a new contact to your directory."}
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-6">
          <div className="grid gap-4 sm:grid-cols-2">
            <Field className="sm:col-span-2" data-invalid={!!errors.name}>
              <FieldLabel htmlFor="contact-name">Name</FieldLabel>
              <Input
                id="contact-name"
                value={values.name}
                onChange={(event) => setField("name", event.target.value)}
                aria-invalid={errors.name ? true : undefined}
                aria-describedby={errors.name ? "contact-name-error" : undefined}
              />
              {errors.name && <FieldError id="contact-name-error">{errors.name}</FieldError>}
            </Field>

            <Field data-invalid={!!errors.contactType}>
              <FieldLabel htmlFor="contact-type">Type</FieldLabel>
              <Select
                items={CONTACT_TYPES}
                value={values.contactType}
                disabled={mode === "edit"}
                onValueChange={(value) =>
                  setField("contactType", (value as ContactType) ?? "client")
                }
              >
                <SelectTrigger
                  id="contact-type"
                  className="w-full"
                  aria-invalid={errors.contactType ? true : undefined}
                  aria-describedby={
                    errors.contactType ? "contact-type-error" : undefined
                  }
                >
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(CONTACT_TYPES).map(([value, label]) => (
                    <SelectItem key={value} value={value}>
                      {label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.contactType && (
                <FieldError id="contact-type-error">
                  {errors.contactType}
                </FieldError>
              )}
            </Field>

            <Field data-invalid={!!errors.email}>
              <FieldLabel htmlFor="contact-email">Email</FieldLabel>
              <Input
                id="contact-email"
                type="email"
                value={values.email}
                onChange={(event) => setField("email", event.target.value)}
                aria-invalid={errors.email ? true : undefined}
                aria-describedby={errors.email ? "contact-email-error" : undefined}
              />
              {errors.email && <FieldError id="contact-email-error">{errors.email}</FieldError>}
            </Field>

            <Field data-invalid={!!errors.phone}>
              <FieldLabel htmlFor="contact-phone">Phone</FieldLabel>
              <Input
                id="contact-phone"
                type="tel"
                value={values.phone}
                onChange={(event) => setField("phone", event.target.value)}
                aria-invalid={errors.phone ? true : undefined}
                aria-describedby={errors.phone ? "contact-phone-error" : undefined}
              />
              {errors.phone && <FieldError id="contact-phone-error">{errors.phone}</FieldError>}
            </Field>

            <Field data-invalid={!!errors.taxNumber}>
              <FieldLabel htmlFor="contact-tax-number">Tax number</FieldLabel>
              <Input
                id="contact-tax-number"
                value={values.taxNumber}
                onChange={(event) => setField("taxNumber", event.target.value)}
                aria-invalid={errors.taxNumber ? true : undefined}
                aria-describedby={
                  errors.taxNumber ? "contact-tax-number-error" : undefined
                }
              />
              {errors.taxNumber && (
                <FieldError id="contact-tax-number-error">
                  {errors.taxNumber}
                </FieldError>
              )}
            </Field>

            <Field className="sm:col-span-2" data-invalid={!!errors.address}>
              <FieldLabel htmlFor="contact-address">Address</FieldLabel>
              <Textarea
                id="contact-address"
                value={values.address}
                onChange={(event) => setField("address", event.target.value)}
                aria-invalid={errors.address ? true : undefined}
                aria-describedby={errors.address ? "contact-address-error" : undefined}
              />
              {errors.address && <FieldError id="contact-address-error">{errors.address}</FieldError>}
            </Field>
          </div>

          {formError && <FieldError id="contact-form-error">{formError}</FieldError>}

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
              {mode === "edit" ? "Save changes" : "Create contact"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
