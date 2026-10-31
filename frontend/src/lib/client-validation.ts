import type { ClientInput } from "@/services/clients-api";

export interface ClientFormValues {
  name: string;
  email: string;
  phone: string;
  address: string;
  type: string;
  cifId: string;
}

export type ClientFormErrors = Partial<Record<keyof ClientFormValues, string>>;

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function createEmptyClientForm(): ClientFormValues {
  return {
    name: "",
    email: "",
    phone: "",
    address: "",
    type: "entreprise",
    cifId: "",
  };
}

export function toClientFormValues(client: {
  name: string;
  email: string | null;
  phone: string | null;
  address: string | null;
  type: string;
  cifId: string | null;
}): ClientFormValues {
  return {
    name: client.name,
    email: client.email ?? "",
    phone: client.phone ?? "",
    address: client.address ?? "",
    type: client.type,
    cifId: client.cifId ?? "",
  };
}

export function validateClientForm(
  values: ClientFormValues,
): ClientFormErrors {
  const errors: ClientFormErrors = {};

  if (!values.name.trim()) {
    errors.name = "Name is required.";
  }

  if (!values.type.trim()) {
    errors.type = "Type is required.";
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

export function toClientInput(values: ClientFormValues): ClientInput {
  return {
    name: values.name.trim(),
    email: toNullable(values.email),
    phone: toNullable(values.phone),
    address: toNullable(values.address),
    type: values.type.trim(),
    cifId: toNullable(values.cifId),
  };
}
