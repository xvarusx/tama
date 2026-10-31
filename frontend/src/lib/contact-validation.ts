import type {
  ContactInput,
  ContactType,
  ContactUpdateInput,
} from "@/services/contacts-api";

export interface ContactFormValues {
  name: string;
  email: string;
  phone: string;
  address: string;
  contactType: ContactType;
  taxNumber: string;
}

export type ContactFormErrors = Partial<
  Record<keyof ContactFormValues, string>
>;

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function createEmptyContactForm(): ContactFormValues {
  return {
    name: "",
    email: "",
    phone: "",
    address: "",
    contactType: "client",
    taxNumber: "",
  };
}

export function toContactFormValues(contact: {
  name: string;
  email: string | null;
  phone: string | null;
  address: string | null;
  contactType: ContactType;
  taxNumber: string | null;
}): ContactFormValues {
  return {
    name: contact.name,
    email: contact.email ?? "",
    phone: contact.phone ?? "",
    address: contact.address ?? "",
    contactType: contact.contactType,
    taxNumber: contact.taxNumber ?? "",
  };
}

export function validateContactForm(
  values: ContactFormValues,
): ContactFormErrors {
  const errors: ContactFormErrors = {};

  if (!values.name.trim()) {
    errors.name = "Name is required.";
  }

  if (!values.contactType.trim()) {
    errors.contactType = "Type is required.";
  }

  const email = values.email.trim();

  if (email && !EMAIL_PATTERN.test(email)) {
    errors.email = "Enter a valid email address.";
  }

  return errors;
}

function toNullable(value: string): string | null {
  const trimmed = value.trim();

  return trimmed === "" ? null : trimmed;
}

export function toContactInput(values: ContactFormValues): ContactInput {
  return {
    name: values.name.trim(),
    email: toNullable(values.email),
    phone: toNullable(values.phone),
    address: toNullable(values.address),
    contactType: values.contactType,
    taxNumber: toNullable(values.taxNumber),
  };
}

export function toContactUpdateInput(
  values: ContactFormValues,
): ContactUpdateInput {
  return {
    name: values.name.trim(),
    email: toNullable(values.email),
    phone: toNullable(values.phone),
    address: toNullable(values.address),
    taxNumber: toNullable(values.taxNumber),
  };
}
