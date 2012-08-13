class CreateOfUsers < ActiveRecord::Migration
  def change
    create_table :of_users do |t|
      t.string :username
      t.string :encrypted_password
      t.string :name
      t.string :email

      t.timestamps
    end
  end
end
